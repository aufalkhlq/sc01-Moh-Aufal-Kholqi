<?php

namespace App\Domain\Booking\DTOs;

readonly class BookingDTO
{
    public function __construct(
        public int $roomId,
        public int $userId,
        public string $title,
        public TimeRange $timeRange,
        public ?int $recurrenceRuleId = null,
    ) {}
}
