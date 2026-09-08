<?php

namespace Tests\Unit\Domain;

use App\Domain\Booking\DTOs\TimeRange;
use App\Domain\Booking\Services\OverlapDetectionService;
use PHPUnit\Framework\TestCase;

class OverlapDetectionServiceTest extends TestCase
{
    private OverlapDetectionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new OverlapDetectionService;
    }

    /**
     * Case 1: Identical range [10:00, 11:00] vs [10:00, 11:00] -> CONFLICT
     */
    public function test_identical_ranges_conflict(): void
    {
        $existing = new TimeRange('2026-09-10 10:00:00', '2026-09-10 11:00:00');
        $target = new TimeRange('2026-09-10 10:00:00', '2026-09-10 11:00:00');

        $this->assertTrue(
            $this->service->doesOverlap($target, $existing),
            'Identical time ranges must conflict.'
        );
    }

    /**
     * Case 2a: Enclosed / Nested - Target is inside existing [10:15, 10:45] inside [10:00, 11:00] -> CONFLICT
     */
    public function test_enclosed_range_target_inside_existing_conflicts(): void
    {
        $existing = new TimeRange('2026-09-10 10:00:00', '2026-09-10 11:00:00');
        $target = new TimeRange('2026-09-10 10:15:00', '2026-09-10 10:45:00');

        $this->assertTrue(
            $this->service->doesOverlap($target, $existing),
            'Target entirely inside existing range must conflict.'
        );
    }

    /**
     * Case 2b: Enclosed / Nested - Target encloses existing [09:00, 12:00] encloses [10:00, 11:00] -> CONFLICT
     */
    public function test_enclosed_range_target_enclosing_existing_conflicts(): void
    {
        $existing = new TimeRange('2026-09-10 10:00:00', '2026-09-10 11:00:00');
        $target = new TimeRange('2026-09-10 09:00:00', '2026-09-10 12:00:00');

        $this->assertTrue(
            $this->service->doesOverlap($target, $existing),
            'Target that completely encloses existing range must conflict.'
        );
    }

    /**
     * Case 3: Overlaps at Start - Target begins before and ends inside [09:30, 10:30] vs [10:00, 11:00] -> CONFLICT
     */
    public function test_overlaps_at_start_conflicts(): void
    {
        $existing = new TimeRange('2026-09-10 10:00:00', '2026-09-10 11:00:00');
        $target = new TimeRange('2026-09-10 09:30:00', '2026-09-10 10:30:00');

        $this->assertTrue(
            $this->service->doesOverlap($target, $existing),
            'Target overlapping start of existing booking must conflict.'
        );
    }

    /**
     * Case 4: Overlaps at End - Target begins inside and ends after [10:30, 11:30] vs [10:00, 11:00] -> CONFLICT
     */
    public function test_overlaps_at_end_conflicts(): void
    {
        $existing = new TimeRange('2026-09-10 10:00:00', '2026-09-10 11:00:00');
        $target = new TimeRange('2026-09-10 10:30:00', '2026-09-10 11:30:00');

        $this->assertTrue(
            $this->service->doesOverlap($target, $existing),
            'Target overlapping end of existing booking must conflict.'
        );
    }

    /**
     * Case 5: Adjacent at boundary - Target starts exactly when existing ends [11:00, 12:00] vs [10:00, 11:00] -> ALLOWED
     */
    public function test_adjacent_ranges_after_allowed(): void
    {
        $existing = new TimeRange('2026-09-10 10:00:00', '2026-09-10 11:00:00');
        $target = new TimeRange('2026-09-10 11:00:00', '2026-09-10 12:00:00');

        $this->assertFalse(
            $this->service->doesOverlap($target, $existing),
            'Adjacent booking starting exactly at previous end time must be allowed.'
        );
    }

    /**
     * Case 5b: Adjacent at boundary - Target ends exactly when existing starts [09:00, 10:00] vs [10:00, 11:00] -> ALLOWED
     */
    public function test_adjacent_ranges_before_allowed(): void
    {
        $existing = new TimeRange('2026-09-10 10:00:00', '2026-09-10 11:00:00');
        $target = new TimeRange('2026-09-10 09:00:00', '2026-09-10 10:00:00');

        $this->assertFalse(
            $this->service->doesOverlap($target, $existing),
            'Adjacent booking ending exactly at next start time must be allowed.'
        );
    }

    /**
     * Case 6: Completely disjoint ranges -> ALLOWED
     */
    public function test_disjoint_ranges_allowed(): void
    {
        $existing = new TimeRange('2026-09-10 10:00:00', '2026-09-10 11:00:00');
        $target = new TimeRange('2026-09-10 14:00:00', '2026-09-10 15:00:00');

        $this->assertFalse(
            $this->service->doesOverlap($target, $existing),
            'Disjoint time ranges must not conflict.'
        );
    }

    /**
     * Case 7: Buffer time enforcement
     */
    public function test_buffer_time_creates_conflict_when_adjacent(): void
    {
        $existing = new TimeRange('2026-09-10 10:00:00', '2026-09-10 11:00:00');
        $target = new TimeRange('2026-09-10 11:00:00', '2026-09-10 12:00:00');

        // With 15 minutes buffer, 11:00 is too soon
        $this->assertTrue(
            $this->service->doesOverlap($target, $existing, bufferMinutes: 15),
            'Booking starting at 11:00 must conflict if 15m buffer is required.'
        );

        // Target starting after buffer (11:15) should be allowed
        $targetAfterBuffer = new TimeRange('2026-09-10 11:15:00', '2026-09-10 12:15:00');
        $this->assertFalse(
            $this->service->doesOverlap($targetAfterBuffer, $existing, bufferMinutes: 15),
            'Booking starting after 15m buffer must be allowed.'
        );
    }
}
