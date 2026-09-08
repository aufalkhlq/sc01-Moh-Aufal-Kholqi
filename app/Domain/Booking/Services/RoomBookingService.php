<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\DTOs\BookingDTO;
use App\Domain\Booking\DTOs\RecurringBookingDTO;
use App\Domain\Booking\DTOs\TimeRange;
use App\Domain\Booking\Exceptions\OperatingHoursException;
use App\Domain\Booking\Exceptions\RoomInactiveException;
use App\Domain\Booking\Exceptions\ScheduleConflictException;
use App\Domain\Booking\Exceptions\UnauthorizedBookingException;
use App\Models\Booking;
use App\Models\BookingHistory;
use App\Models\RecurrenceRule;
use App\Models\Room;
use App\Models\RoomOperatingHour;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RoomBookingService
{
    public function __construct(
        protected OverlapDetectionService $overlapDetector
    ) {}

    /**
     * Creates a new booking with pessimistic concurrency locking and overlap prevention.
     */
    public function createBooking(BookingDTO $dto): Booking
    {
        return DB::transaction(function () use ($dto) {
            /** @var Room $room */
            $room = Room::query()->lockForUpdate()->findOrFail($dto->roomId);

            if (! $room->is_active) {
                throw new RoomInactiveException("Room '{$room->name}' is currently inactive.");
            }

            $this->validateOperatingHours($room, $dto->timeRange);
            $this->assertNoOverlap($room, $dto->timeRange);

            /** @var Booking $booking */
            $booking = Booking::create([
                'room_id' => $room->id,
                'user_id' => $dto->userId,
                'recurrence_rule_id' => $dto->recurrenceRuleId,
                'title' => $dto->title,
                'start_time' => $dto->timeRange->start(),
                'end_time' => $dto->timeRange->end(),
                'status' => Booking::STATUS_CONFIRMED,
            ]);

            BookingHistory::create([
                'booking_id' => $booking->id,
                'user_id' => $dto->userId,
                'action' => BookingHistory::ACTION_CREATED,
                'old_data' => null,
                'new_data' => [
                    'room_id' => $booking->room_id,
                    'title' => $booking->title,
                    'start_time' => $booking->start_time->toIso8601String(),
                    'end_time' => $booking->end_time->toIso8601String(),
                    'status' => $booking->status,
                ],
                'reason' => 'Initial booking creation',
            ]);

            return $booking->load(['room', 'user']);
        });
    }

    /**
     * Cancels an existing booking. Only the booking owner is authorized.
     */
    public function cancelBooking(int $bookingId, int $userId, ?string $reason = null): Booking
    {
        return DB::transaction(function () use ($bookingId, $userId, $reason) {
            /** @var Booking $booking */
            $booking = Booking::query()->lockForUpdate()->findOrFail($bookingId);

            if ($booking->user_id !== $userId) {
                throw new UnauthorizedBookingException('You are not authorized to cancel this booking.');
            }

            if ($booking->isCancelled()) {
                return $booking;
            }

            $oldData = [
                'status' => $booking->status,
                'start_time' => $booking->start_time->toIso8601String(),
                'end_time' => $booking->end_time->toIso8601String(),
            ];

            $booking->update([
                'status' => Booking::STATUS_CANCELLED,
                'cancellation_reason' => $reason,
                'cancelled_at' => Carbon::now('UTC'),
            ]);

            BookingHistory::create([
                'booking_id' => $booking->id,
                'user_id' => $userId,
                'action' => BookingHistory::ACTION_CANCELLED,
                'old_data' => $oldData,
                'new_data' => [
                    'status' => Booking::STATUS_CANCELLED,
                    'cancellation_reason' => $reason,
                    'cancelled_at' => $booking->cancelled_at->toIso8601String(),
                ],
                'reason' => $reason ?? 'Cancelled by user',
            ]);

            return $booking->fresh(['room', 'user']);
        });
    }

    /**
     * Reschedules an existing booking to a new time range.
     */
    public function rescheduleBooking(int $bookingId, int $userId, TimeRange $newRange, ?string $reason = null): Booking
    {
        return DB::transaction(function () use ($bookingId, $userId, $newRange, $reason) {
            /** @var Booking $booking */
            $booking = Booking::query()->lockForUpdate()->findOrFail($bookingId);

            if ($booking->user_id !== $userId) {
                throw new UnauthorizedBookingException('You are not authorized to reschedule this booking.');
            }

            if ($booking->isCancelled()) {
                throw new InvalidArgumentException('Cannot reschedule a cancelled booking.');
            }

            /** @var Room $room */
            $room = Room::query()->lockForUpdate()->findOrFail($booking->room_id);

            if (! $room->is_active) {
                throw new RoomInactiveException("Room '{$room->name}' is currently inactive.");
            }

            $this->validateOperatingHours($room, $newRange);
            $this->assertNoOverlap($room, $newRange, ignoreBookingId: $booking->id);

            $oldData = [
                'start_time' => $booking->start_time->toIso8601String(),
                'end_time' => $booking->end_time->toIso8601String(),
            ];

            $booking->update([
                'start_time' => $newRange->start(),
                'end_time' => $newRange->end(),
            ]);

            BookingHistory::create([
                'booking_id' => $booking->id,
                'user_id' => $userId,
                'action' => BookingHistory::ACTION_RESCHEDULED,
                'old_data' => $oldData,
                'new_data' => [
                    'start_time' => $newRange->start()->toIso8601String(),
                    'end_time' => $newRange->end()->toIso8601String(),
                ],
                'reason' => $reason ?? 'Rescheduled by user',
            ]);

            return $booking->fresh(['room', 'user']);
        });
    }

    /**
     * Creates recurring bookings (daily/weekly) with support for exception dates.
     * Atomically rolls back if any requested slot produces an overlap collision.
     *
     * @return array{rule: RecurrenceRule, bookings: array<Booking>}
     */
    public function createRecurringBooking(RecurringBookingDTO $dto): array
    {
        return DB::transaction(function () use ($dto) {
            /** @var Room $room */
            $room = Room::query()->lockForUpdate()->findOrFail($dto->roomId);

            if (! $room->is_active) {
                throw new RoomInactiveException("Room '{$room->name}' is currently inactive.");
            }

            $rule = RecurrenceRule::create([
                'frequency' => $dto->frequency,
                'start_date' => $dto->startDate,
                'end_date' => $dto->endDate,
                'exception_dates' => $dto->exceptionDates,
            ]);

            $step = $dto->frequency === 'daily' ? '1 day' : '1 week';
            $period = CarbonPeriod::create($dto->startDate, $step, $dto->endDate);

            $createdBookings = [];
            $exceptionLookup = array_flip($dto->exceptionDates);

            foreach ($period as $date) {
                $dateString = $date->format('Y-m-d');
                if (isset($exceptionLookup[$dateString])) {
                    continue; // Skip exception date
                }

                // Parse time in specified timezone, convert to UTC
                $occurrenceStart = Carbon::parse("{$dateString} {$dto->startTimeOfDay}", $dto->timezone)->utc();
                $occurrenceEnd = Carbon::parse("{$dateString} {$dto->endTimeOfDay}", $dto->timezone)->utc();

                $range = new TimeRange($occurrenceStart, $occurrenceEnd);

                $this->validateOperatingHours($room, $range);
                $this->assertNoOverlap($room, $range);

                $booking = Booking::create([
                    'room_id' => $room->id,
                    'user_id' => $dto->userId,
                    'recurrence_rule_id' => $rule->id,
                    'title' => "{$dto->title} ({$dateString})",
                    'start_time' => $range->start(),
                    'end_time' => $range->end(),
                    'status' => Booking::STATUS_CONFIRMED,
                ]);

                BookingHistory::create([
                    'booking_id' => $booking->id,
                    'user_id' => $dto->userId,
                    'action' => BookingHistory::ACTION_CREATED,
                    'new_data' => [
                        'room_id' => $room->id,
                        'title' => $booking->title,
                        'start_time' => $booking->start_time->toIso8601String(),
                        'end_time' => $booking->end_time->toIso8601String(),
                    ],
                    'reason' => "Recurring series ({$dto->frequency})",
                ]);

                $createdBookings[] = $booking;
            }

            return [
                'rule' => $rule,
                'bookings' => $createdBookings,
            ];
        });
    }

    /**
     * Search available rooms within a time range and meeting capacity requirement.
     *
     * @return Collection<int, Room>
     */
    public function searchAvailableRooms(TimeRange $range, int $minCapacity = 1): Collection
    {
        $rooms = Room::query()
            ->active()
            ->minCapacity(max(1, $minCapacity))
            ->with(['operatingHours'])
            ->get();

        return $rooms->filter(function (Room $room) use ($range) {
            // Check operating hours
            try {
                $this->validateOperatingHours($room, $range);
            } catch (OperatingHoursException) {
                return false;
            }

            // Check bookings overlap
            // Check bookings overlap within the target window (accounting for buffer)
            $maxBuffer = max(0, (int) $room->buffer_minutes);
            $windowStart = $range->start()->copy()->subMinutes($maxBuffer);
            $windowEnd = $range->end()->copy()->addMinutes($maxBuffer);

            $bookings = Booking::query()
                ->where('room_id', $room->id)
                ->where('status', Booking::STATUS_CONFIRMED)
                ->where('start_time', '<', $windowEnd)
                ->where('end_time', '>', $windowStart)
                ->get();

            $conflict = $this->overlapDetector->findConflictingBooking($range, $bookings, $room->buffer_minutes);

            return $conflict === null;
        })->values();
    }

    /**
     * Search available rooms for a recurring schedule (daily/weekly).
     * A room is available if and only if EVERY recurring occurrence slot is free
     * and strictly within operating hours.
     *
     * @param  array<int, string>  $exceptionDates
     * @return Collection<int, Room>
     */
    public function searchAvailableRoomsRecurring(
        string $startDate,
        string $endDate,
        string $startTimeOfDay,
        string $endTimeOfDay,
        string $frequency = 'daily',
        array $exceptionDates = [],
        string $timezone = 'UTC',
        int $minCapacity = 1
    ): Collection {
        $rooms = Room::query()
            ->active()
            ->minCapacity(max(1, $minCapacity))
            ->with(['operatingHours'])
            ->get();

        $step = $frequency === 'daily' ? '1 day' : '1 week';
        $period = CarbonPeriod::create($startDate, $step, $endDate);
        $exceptionLookup = array_flip($exceptionDates);

        // Build all occurrence TimeRanges
        $occurrenceRanges = [];
        foreach ($period as $date) {
            $dateString = $date->format('Y-m-d');
            if (isset($exceptionLookup[$dateString])) {
                continue;
            }

            $occurrenceStart = Carbon::parse("{$dateString} {$startTimeOfDay}", $timezone)->utc();
            $occurrenceEnd = Carbon::parse("{$dateString} {$endTimeOfDay}", $timezone)->utc();

            $occurrenceRanges[] = new TimeRange($occurrenceStart, $occurrenceEnd);
        }

        if (empty($occurrenceRanges)) {
            return collect();
        }

        return $rooms->filter(function (Room $room) use ($occurrenceRanges) {
            $maxBuffer = max(0, (int) $room->buffer_minutes);

            foreach ($occurrenceRanges as $range) {
                // 1. Check operating hours for this specific occurrence
                try {
                    $this->validateOperatingHours($room, $range);
                } catch (OperatingHoursException) {
                    return false;
                }

                // 2. Check overlap conflict with existing bookings for this specific occurrence
                $windowStart = $range->start()->copy()->subMinutes($maxBuffer);
                $windowEnd = $range->end()->copy()->addMinutes($maxBuffer);

                $bookings = Booking::query()
                    ->where('room_id', $room->id)
                    ->where('status', Booking::STATUS_CONFIRMED)
                    ->where('start_time', '<', $windowEnd)
                    ->where('end_time', '>', $windowStart)
                    ->get();

                $conflict = $this->overlapDetector->findConflictingBooking($range, $bookings, $room->buffer_minutes);
                if ($conflict !== null) {
                    return false;
                }
            }

            return true;
        })->values();
    }

    /**
     * Validates that the requested range is within the room's operating hours (if configured).
     */
    protected function validateOperatingHours(Room $room, TimeRange $range): void
    {
        $start = $range->start();
        $end = $range->end();

        $hasOperatingHours = $room->relationLoaded('operatingHours')
            ? $room->operatingHours->isNotEmpty()
            : $room->operatingHours()->exists();

        // If no operating hours are configured at all, the room operates 24/7
        if (! $hasOperatingHours) {
            return;
        }

        $operatingHours = $room->relationLoaded('operatingHours')
            ? $room->operatingHours->firstWhere('day_of_week', $start->dayOfWeek)
            : RoomOperatingHour::query()
                ->where('room_id', $room->id)
                ->where('day_of_week', $start->dayOfWeek)
                ->first();

        // If the room has configured operating hours, any day without an entry is closed
        if (! $operatingHours) {
            throw new OperatingHoursException(
                "Ruang '{$room->name}' tidak beroperasi pada hari yang dipilih.",
                [
                    'room_id' => $room->id,
                    'day_of_week' => $start->dayOfWeek,
                    'requested_start' => $start->toIso8601String(),
                    'requested_end' => $end->toIso8601String(),
                ]
            );
        }

        $openTime = Carbon::parse($start->format('Y-m-d').' '.$operatingHours->open_time, 'UTC');
        $closeTime = Carbon::parse($start->format('Y-m-d').' '.$operatingHours->close_time, 'UTC');

        if ($start->lessThan($openTime) || $end->greaterThan($closeTime)) {
            throw new OperatingHoursException(
                "Booking time must be within operating hours ({$operatingHours->open_time} - {$operatingHours->close_time}).",
                [
                    'room_id' => $room->id,
                    'day_of_week' => $start->dayOfWeek,
                    'open_time' => $operatingHours->open_time,
                    'close_time' => $operatingHours->close_time,
                    'requested_start' => $start->toIso8601String(),
                    'requested_end' => $end->toIso8601String(),
                ]
            );
        }
    }

    /**
     * Asserts that no confirmed booking overlaps with targetRange for the given room.
     */
    protected function assertNoOverlap(Room $room, TimeRange $range, ?int $ignoreBookingId = null): void
    {
        $buffer = $room->buffer_minutes;

        $existingBookings = Booking::query()
            ->where('room_id', $room->id)
            ->where('status', Booking::STATUS_CONFIRMED)
            ->when($ignoreBookingId !== null, fn ($q) => $q->where('id', '!=', $ignoreBookingId))
            ->lockForUpdate()
            ->get();

        $conflicting = $this->overlapDetector->findConflictingBooking($range, $existingBookings, $buffer, $ignoreBookingId);

        if ($conflicting !== null) {
            throw new ScheduleConflictException(
                message: "Room '{$room->name}' is already booked during this time range (or within the {$buffer}m required buffer).",
                conflictingBooking: $conflicting
            );
        }
    }
}
