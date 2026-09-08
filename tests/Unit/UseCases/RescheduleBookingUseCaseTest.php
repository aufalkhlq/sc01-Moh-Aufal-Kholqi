<?php

namespace Tests\Unit\UseCases;

use App\Domain\Booking\DTOs\TimeRange;
use App\Domain\Booking\Exceptions\OperatingHoursException;
use App\Domain\Booking\Exceptions\ScheduleConflictException;
use App\Domain\Booking\Exceptions\UnauthorizedBookingException;
use App\Domain\Booking\Services\OverlapDetectionService;
use App\Domain\Booking\UseCases\RescheduleBookingUseCase;
use App\Models\Booking;
use App\Models\BookingHistory;
use App\Models\Room;
use App\Models\RoomOperatingHour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RescheduleBookingUseCaseTest extends TestCase
{
    use RefreshDatabase;

    private RescheduleBookingUseCase $useCase;

    private Room $room;

    private User $owner;

    private User $otherUser;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useCase = new RescheduleBookingUseCase(new OverlapDetectionService);

        $this->room = Room::factory()->create([
            'buffer_minutes' => 0,
        ]);
        $this->owner = User::factory()->create(['name' => 'Owner']);
        $this->otherUser = User::factory()->create(['name' => 'Other User']);

        $this->booking = Booking::create([
            'room_id' => $this->room->id,
            'user_id' => $this->owner->id,
            'title' => 'Product Demo',
            'start_time' => '2026-09-10 10:00:00',
            'end_time' => '2026-09-10 11:00:00',
            'status' => Booking::STATUS_CONFIRMED,
        ]);
    }

    public function test_reschedules_booking_successfully_with_audit_trail(): void
    {
        $newRange = new TimeRange('2026-09-10 14:00:00', '2026-09-10 15:00:00');

        $updated = $this->useCase->execute(
            $this->booking->id,
            $this->owner->id,
            $newRange,
            'Moved to afternoon session'
        );

        $this->assertEquals('2026-09-10 14:00:00', $updated->start_time->format('Y-m-d H:i:s'));
        $this->assertEquals('2026-09-10 15:00:00', $updated->end_time->format('Y-m-d H:i:s'));

        $this->assertDatabaseHas('bookings', [
            'id' => $this->booking->id,
            'start_time' => '2026-09-10 14:00:00',
            'end_time' => '2026-09-10 15:00:00',
        ]);

        $this->assertDatabaseHas('booking_histories', [
            'booking_id' => $this->booking->id,
            'user_id' => $this->owner->id,
            'action' => BookingHistory::ACTION_RESCHEDULED,
            'reason' => 'Moved to afternoon session',
        ]);
    }

    public function test_throws_unauthorized_when_non_owner_attempts_reschedule(): void
    {
        $newRange = new TimeRange('2026-09-10 14:00:00', '2026-09-10 15:00:00');

        $this->expectException(UnauthorizedBookingException::class);
        $this->expectExceptionMessage('You are not authorized to reschedule this booking.');

        $this->useCase->execute($this->booking->id, $this->otherUser->id, $newRange);
    }

    public function test_throws_conflict_when_new_schedule_overlaps_another_booking(): void
    {
        // Another confirmed booking at 14:00 - 15:00
        Booking::create([
            'room_id' => $this->room->id,
            'user_id' => $this->otherUser->id,
            'title' => 'Design Review',
            'start_time' => '2026-09-10 14:00:00',
            'end_time' => '2026-09-10 15:00:00',
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        // Attempt to reschedule to 14:30 - 15:30 (overlaps Design Review)
        $newRange = new TimeRange('2026-09-10 14:30:00', '2026-09-10 15:30:00');

        $this->expectException(ScheduleConflictException::class);

        $this->useCase->execute($this->booking->id, $this->owner->id, $newRange);
    }

    public function test_throws_operating_hours_exception_when_new_schedule_outside_hours(): void
    {
        // Operating hour Thursday 08:00 - 17:00
        RoomOperatingHour::create([
            'room_id' => $this->room->id,
            'day_of_week' => 4,
            'open_time' => '08:00:00',
            'close_time' => '17:00:00',
        ]);

        // Attempt reschedule to 18:00 - 19:00
        $newRange = new TimeRange('2026-09-10 18:00:00', '2026-09-10 19:00:00');

        $this->expectException(OperatingHoursException::class);

        $this->useCase->execute($this->booking->id, $this->owner->id, $newRange);
    }
}
