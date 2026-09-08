<?php

namespace App\Domain\Booking\DTOs;

use Carbon\Carbon;
use DateTimeInterface;
use InvalidArgumentException;

final class TimeRange
{
    private Carbon $start;

    private Carbon $end;

    public function __construct(DateTimeInterface|string $start, DateTimeInterface|string $end)
    {
        $this->start = $this->parseToUtc($start);
        $this->end = $this->parseToUtc($end);

        if ($this->end->lessThanOrEqualTo($this->start)) {
            throw new InvalidArgumentException(
                "End time ({$this->end->toIso8601String()}) must be strictly after start time ({$this->start->toIso8601String()})."
            );
        }
    }

    public static function from(DateTimeInterface|string $start, DateTimeInterface|string $end): self
    {
        return new self($start, $end);
    }

    private function parseToUtc(DateTimeInterface|string $datetime): Carbon
    {
        if ($datetime instanceof Carbon) {
            return $datetime->copy()->utc();
        }

        if ($datetime instanceof DateTimeInterface) {
            return Carbon::instance($datetime)->utc();
        }

        return Carbon::parse($datetime)->utc();
    }

    public function start(): Carbon
    {
        return $this->start->copy();
    }

    public function end(): Carbon
    {
        return $this->end->copy();
    }

    public function durationMinutes(): int
    {
        return (int) $this->start->diffInMinutes($this->end);
    }

    /**
     * Checks if this range overlaps with another range, considering optional buffer minutes.
     * Overlap occurs when:
     * (Start_A < End_B + buffer) AND (End_A + buffer > Start_B)
     * When buffer = 0:
     * (Start_A < End_B) AND (End_A > Start_B)
     * Note that adjacent intervals (End_A == Start_B) will evaluate to false (no overlap).
     */
    public function overlaps(TimeRange $other, int $bufferMinutes = 0): bool
    {
        $effectiveThisEnd = $this->end->copy()->addMinutes($bufferMinutes);
        $effectiveOtherEnd = $other->end->copy()->addMinutes($bufferMinutes);

        return $this->start->lessThan($effectiveOtherEnd)
            && $effectiveThisEnd->greaterThan($other->start);
    }

    public function contains(DateTimeInterface|string $time): bool
    {
        $target = $this->parseToUtc($time);

        return $target->greaterThanOrEqualTo($this->start) && $target->lessThan($this->end);
    }

    public function toArray(): array
    {
        return [
            'start_time' => $this->start->toIso8601String(),
            'end_time' => $this->end->toIso8601String(),
        ];
    }
}
