<?php

namespace App\Domain\Booking\DTOs;

use Carbon\Carbon;
use InvalidArgumentException;

readonly class RecurringBookingDTO
{
    /**
     * @param  array<string>  $exceptionDates
     */
    public function __construct(
        public int $roomId,
        public int $userId,
        public string $title,
        public string $startTimeOfDay, // e.g. "09:00:00"
        public string $endTimeOfDay,   // e.g. "10:30:00"
        public string $frequency,      // "daily" or "weekly"
        public string $startDate,      // "2026-09-10"
        public string $endDate,        // "2026-09-30"
        public array $exceptionDates = [], // ["2026-09-15"]
        public string $timezone = 'UTC'
    ) {
        if (! in_array($frequency, ['daily', 'weekly'], true)) {
            throw new InvalidArgumentException("Frequency must be 'daily' or 'weekly'.");
        }

        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        if ($end->lessThan($start)) {
            throw new InvalidArgumentException('End date must be on or after start date.');
        }
    }
}
