<?php

namespace App\Domain\Booking\UseCases;

use App\Domain\Booking\DTOs\BookingDTO;
use App\Domain\Booking\DTOs\TimeRange;
use App\Domain\Booking\Exceptions\OperatingHoursException;
use App\Domain\Booking\Exceptions\RoomInactiveException;
use App\Domain\Booking\Exceptions\ScheduleConflictException;
use App\Domain\Booking\Services\OverlapDetectionService;
use App\Models\Booking;
use App\Models\BookingHistory;
use App\Models\Room;
use App\Models\RoomOperatingHour;
use Illuminate\Support\Facades\DB;

class CreateBookingUseCase
{
    public function __construct(
        protected OverlapDetectionService $overlapDetector
    ) {}

    /**
     * Executes the booking creation with pessimistic concurrency locking and business rule validations.
     *
     * @throws RoomInactiveException
     * @throws OperatingHoursException
     * @throws ScheduleConflictException
     */
    public function execute(BookingDTO $dto): Booking
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

    protected function validateOperatingHours(Room $room, TimeRange $range): void
    {
        $start = $range->start();
        $end = $range->end();

        $operatingHours = RoomOperatingHour::query()
            ->where('room_id', $room->id)
            ->where('day_of_week', $start->dayOfWeek)
            ->first();

        if (! $operatingHours) {
            return; // No restriction for this day
        }

        $openDateTime = $start->copy()->setTimeFromTimeString($operatingHours->open_time);
        $closeDateTime = $start->copy()->setTimeFromTimeString($operatingHours->close_time);

        if ($start->lt($openDateTime) || $end->gt($closeDateTime)) {
            throw new OperatingHoursException(
                "Booking range [{$start->format('H:i')}, {$end->format('H:i')}] violates room operating hours ".
                "[{$openDateTime->format('H:i')}, {$closeDateTime->format('H:i')}]."
            );
        }
    }

    protected function assertNoOverlap(Room $room, TimeRange $range, ?int $excludeBookingId = null): void
    {
        $query = Booking::query()
            ->where('room_id', $room->id)
            ->where('status', Booking::STATUS_CONFIRMED);

        if ($excludeBookingId !== null) {
            $query->where('id', '!=', $excludeBookingId);
        }

        $existingBookings = $query->lockForUpdate()->get();

        $conflictingBooking = $this->overlapDetector->findConflictingBooking(
            $range,
            $existingBookings,
            $room->buffer_minutes
        );

        if ($conflictingBooking !== null) {
            throw new ScheduleConflictException(
                message: "Room '{$room->name}' is already booked during this time range (or within the {$room->buffer_minutes}m required buffer).",
                conflictingBooking: $conflictingBooking
            );
        }
    }
}
