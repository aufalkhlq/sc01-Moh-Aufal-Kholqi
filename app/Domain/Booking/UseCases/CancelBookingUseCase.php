<?php

namespace App\Domain\Booking\UseCases;

use App\Domain\Booking\Exceptions\UnauthorizedBookingException;
use App\Models\Booking;
use App\Models\BookingHistory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CancelBookingUseCase
{
    /**
     * Cancels an existing booking. Only the booking owner is authorized.
     *
     * @throws UnauthorizedBookingException
     */
    public function execute(int $bookingId, int $userId, ?string $reason = null): Booking
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
                    'cancelled_at' => $booking->cancelled_at?->toIso8601String(),
                ],
                'reason' => $reason ?? 'Booking cancellation by owner',
            ]);

            return $booking->fresh(['room', 'user']);
        });
    }
}
