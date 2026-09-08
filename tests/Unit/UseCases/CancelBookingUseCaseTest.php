<?php

namespace Tests\Unit\UseCases;

use App\Domain\Booking\Exceptions\UnauthorizedBookingException;
use App\Domain\Booking\UseCases\CancelBookingUseCase;
use App\Models\Booking;
use App\Models\BookingHistory;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancelBookingUseCaseTest extends TestCase
{
    use RefreshDatabase;

    private CancelBookingUseCase $useCase;

    private Room $room;

    private User $owner;

    private User $otherUser;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useCase = new CancelBookingUseCase;

        $this->room = Room::factory()->create();
        $this->owner = User::factory()->create(['name' => 'Owner']);
        $this->otherUser = User::factory()->create(['name' => 'Other User']);

        $this->booking = Booking::create([
            'room_id' => $this->room->id,
            'user_id' => $this->owner->id,
            'title' => 'Project Kickoff',
            'start_time' => '2026-09-10 14:00:00',
            'end_time' => '2026-09-10 15:00:00',
            'status' => Booking::STATUS_CONFIRMED,
        ]);
    }

    public function test_cancels_booking_successfully_by_owner_with_audit_trail(): void
    {
        $cancelled = $this->useCase->execute($this->booking->id, $this->owner->id, 'Client postponed meeting');

        $this->assertEquals(Booking::STATUS_CANCELLED, $cancelled->status);
        $this->assertEquals('Client postponed meeting', $cancelled->cancellation_reason);
        $this->assertNotNull($cancelled->cancelled_at);

        $this->assertDatabaseHas('bookings', [
            'id' => $this->booking->id,
            'status' => Booking::STATUS_CANCELLED,
            'cancellation_reason' => 'Client postponed meeting',
        ]);

        $this->assertDatabaseHas('booking_histories', [
            'booking_id' => $this->booking->id,
            'user_id' => $this->owner->id,
            'action' => BookingHistory::ACTION_CANCELLED,
            'reason' => 'Client postponed meeting',
        ]);
    }

    public function test_throws_unauthorized_when_non_owner_attempts_cancellation(): void
    {
        $this->expectException(UnauthorizedBookingException::class);
        $this->expectExceptionMessage('You are not authorized to cancel this booking.');

        $this->useCase->execute($this->booking->id, $this->otherUser->id);
    }

    public function test_cancellation_is_idempotent_when_already_cancelled(): void
    {
        // First cancellation
        $this->useCase->execute($this->booking->id, $this->owner->id, 'First reason');

        // Second cancellation on same booking
        $result = $this->useCase->execute($this->booking->id, $this->owner->id, 'Second reason');

        $this->assertEquals(Booking::STATUS_CANCELLED, $result->status);
        // Only 1 cancellation history entry created
        $this->assertEquals(1, BookingHistory::where('booking_id', $this->booking->id)->where('action', BookingHistory::ACTION_CANCELLED)->count());
    }
}
