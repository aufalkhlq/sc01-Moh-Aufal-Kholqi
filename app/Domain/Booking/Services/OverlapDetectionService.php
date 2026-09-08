<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\DTOs\TimeRange;
use App\Models\Booking;

/**
 * OverlapDetectionService
 *
 * Encapsulates the pure domain logic for interval collision detection.
 * Independent of HTTP, controllers, or database persistence.
 *
 * Rules:
 * Two intervals [S1, E1] and [S2, E2] overlap when S1 < E2 AND E1 > S2.
 * Adjacent intervals (E1 == S2) do NOT overlap when buffer = 0.
 * With buffer B, effective intervals [S1, E1 + B] are evaluated.
 */
class OverlapDetectionService
{
    /**
     * Determines whether targetRange collides with another TimeRange.
     */
    public function doesOverlap(TimeRange $targetRange, TimeRange $existingRange, int $bufferMinutes = 0): bool
    {
        return $targetRange->overlaps($existingRange, $bufferMinutes);
    }

    /**
     * Finds the first conflicting TimeRange among a collection of ranges.
     *
     * @param  iterable<TimeRange>  $existingRanges
     */
    public function findConflictingRange(TimeRange $targetRange, iterable $existingRanges, int $bufferMinutes = 0): ?TimeRange
    {
        foreach ($existingRanges as $range) {
            if ($this->doesOverlap($targetRange, $range, $bufferMinutes)) {
                return $range;
            }
        }

        return null;
    }

    /**
     * Finds the first conflicting Booking entity among a collection of Bookings.
     *
     * @param  iterable<Booking>  $existingBookings
     */
    public function findConflictingBooking(
        TimeRange $targetRange,
        iterable $existingBookings,
        int $bufferMinutes = 0,
        ?int $ignoreBookingId = null
    ): ?Booking {
        foreach ($existingBookings as $booking) {
            if ($ignoreBookingId !== null && $booking->id === $ignoreBookingId) {
                continue;
            }

            if (! $booking->isConfirmed()) {
                continue;
            }

            $bookingRange = new TimeRange($booking->start_time, $booking->end_time);

            if ($this->doesOverlap($targetRange, $bookingRange, $bufferMinutes)) {
                return $booking;
            }
        }

        return null;
    }
}
