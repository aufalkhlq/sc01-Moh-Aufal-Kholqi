<?php

namespace App\Domain\Booking\UseCases;

use App\Domain\Booking\DTOs\TimeRange;
use App\Domain\Booking\Exceptions\OperatingHoursException;
use App\Domain\Booking\Exceptions\ScheduleConflictException;
use App\Domain\Booking\Exceptions\UnauthorizedBookingException;
use App\Domain\Booking\Services\OverlapDetectionService;
use App\Models\Booking;
use App\Models\BookingHistory;
use App\Models\Room;
use App\Models\RoomOperatingHour;
use Illuminate\Support\Facades\DB;

class RescheduleBookingUseCase
{
    public function __construct(
        protected OverlapDetectionService $overlapDetector
    ) {}

    /**
     * Reschedules an existing booking to a new time range.
     *
     * @throws UnauthorizedBookingException
     * @throws OperatingHoursException
     * @throws ScheduleConflictException
     */
    public function execute(int $bookingId, int $userId, TimeRange $newRange, ?string $reason = null): Booking
    {
        return DB::transaction(function () use ($bookingId, $userId, $newRange, $reason) {
            /** @var Booking $booking */
            $booking = Booking::query()->lockForUpdate()->findOrFail($bookingId);

            if ($booking->user_id !== $userId) {
                throw new UnauthorizedBookingException('You are not authorized to reschedule this booking.');
            }

            if ($booking->isCancelled()) {
                throw new \InvalidArgumentException('Cannot reschedule a cancelled booking.');
            }

            /** @var Room $room */
            $room = Room::query()->lockForUpdate()->findOrFail($booking->room_id);

            $this->validateOperatingHours($room, $newRange);
            $this->assertNoOverlap($room, $newRange, $booking->id);

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
                    'start_time' => $booking->start_time->toIso8601String(),
                    'end_time' => $booking->end_time->toIso8601String(),
                ],
                'reason' => $reason ?? 'Rescheduled by booking owner',
            ]);

            return $booking->fresh(['room', 'user']);
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
            return;
        }

        $openDateTime = $start->copy()->setTimeFromTimeString($operatingHours->open_time);
        $closeDateTime = $start->copy()->setTimeFromTimeString($operatingHours->close_time);

        if ($start->lt($openDateTime) || $end->gt($closeDateTime)) {
            throw new OperatingHoursException(
                "Rescheduled range [{$start->format('H:i')}, {$end->format('H:i')}] violates room operating hours ".
                "[{$openDateTime->format('H:i')}, {$closeDateTime->format('H:i')}]."
            );
        }
    }

    protected function assertNoOverlap(Room $room, TimeRange $range, int $excludeBookingId): void
    {
        $existingBookings = Booking::query()
            ->where('room_id', $room->id)
            ->where('status', Booking::STATUS_CONFIRMED)
            ->where('id', '!=', $excludeBookingId)
            ->lockForUpdate()
            ->get();

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
