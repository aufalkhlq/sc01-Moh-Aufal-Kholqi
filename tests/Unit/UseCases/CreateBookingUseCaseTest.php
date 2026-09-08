<?php

namespace Tests\Unit\UseCases;

use App\Domain\Booking\DTOs\BookingDTO;
use App\Domain\Booking\DTOs\TimeRange;
use App\Domain\Booking\Exceptions\OperatingHoursException;
use App\Domain\Booking\Exceptions\RoomInactiveException;
use App\Domain\Booking\Exceptions\ScheduleConflictException;
use App\Domain\Booking\Services\OverlapDetectionService;
use App\Domain\Booking\UseCases\CreateBookingUseCase;
use App\Models\Booking;
use App\Models\BookingHistory;
use App\Models\Room;
use App\Models\RoomOperatingHour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateBookingUseCaseTest extends TestCase
{
    use RefreshDatabase;

    private CreateBookingUseCase $useCase;

    private Room $room;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useCase = new CreateBookingUseCase(new OverlapDetectionService);

        $this->room = Room::factory()->create([
            'name' => 'Ruang Executive',
            'capacity' => 10,
            'buffer_minutes' => 15,
            'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'name' => 'Alice Developer',
        ]);
    }

    public function test_creates_booking_successfully_with_audit_trail(): void
    {
        $dto = new BookingDTO(
            roomId: $this->room->id,
            userId: $this->user->id,
            title: 'Sprint Planning',
            timeRange: new TimeRange('2026-09-10 10:00:00', '2026-09-10 11:00:00')
        );

        $booking = $this->useCase->execute($dto);

        $this->assertInstanceOf(Booking::class, $booking);
        $this->assertEquals('Sprint Planning', $booking->title);
        $this->assertEquals(Booking::STATUS_CONFIRMED, $booking->status);
        $this->assertEquals($this->room->id, $booking->room_id);
        $this->assertEquals($this->user->id, $booking->user_id);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'title' => 'Sprint Planning',
            'status' => 'confirmed',
        ]);

        $this->assertDatabaseHas('booking_histories', [
            'booking_id' => $booking->id,
            'user_id' => $this->user->id,
            'action' => BookingHistory::ACTION_CREATED,
        ]);
    }

    public function test_throws_exception_when_room_is_inactive(): void
    {
        $this->room->update(['is_active' => false]);

        $dto = new BookingDTO(
            roomId: $this->room->id,
            userId: $this->user->id,
            title: 'Standup Meeting',
            timeRange: new TimeRange('2026-09-10 10:00:00', '2026-09-10 11:00:00')
        );

        $this->expectException(RoomInactiveException::class);
        $this->expectExceptionMessage("Room 'Ruang Executive' is currently inactive.");

        $this->useCase->execute($dto);
    }

    public function test_throws_exception_when_outside_operating_hours(): void
    {
        // 2026-09-10 is Thursday (dayOfWeek = 4)
        RoomOperatingHour::create([
            'room_id' => $this->room->id,
            'day_of_week' => 4,
            'open_time' => '08:00:00',
            'close_time' => '17:00:00',
        ]);

        // Attempt booking from 17:00 to 18:00 (exceeds 17:00 close time)
        $dto = new BookingDTO(
            roomId: $this->room->id,
            userId: $this->user->id,
            title: 'Late Night Retrospective',
            timeRange: new TimeRange('2026-09-10 17:00:00', '2026-09-10 18:00:00')
        );

        $this->expectException(OperatingHoursException::class);

        $this->useCase->execute($dto);
    }

    public function test_throws_conflict_when_overlapping_existing_booking(): void
    {
        // Create an existing booking 10:00 - 11:00
        Booking::create([
            'room_id' => $this->room->id,
            'user_id' => $this->user->id,
            'title' => 'Existing Meeting',
            'start_time' => '2026-09-10 10:00:00',
            'end_time' => '2026-09-10 11:00:00',
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        // Attempt overlapping booking 10:30 - 11:30
        $dto = new BookingDTO(
            roomId: $this->room->id,
            userId: $this->user->id,
            title: 'Conflicting Meeting',
            timeRange: new TimeRange('2026-09-10 10:30:00', '2026-09-10 11:30:00')
        );

        $this->expectException(ScheduleConflictException::class);

        $this->useCase->execute($dto);
    }

    public function test_throws_conflict_when_violating_buffer_time(): void
    {
        // Room buffer is 15 minutes.
        // Existing booking 10:00 - 11:00 (buffer extends to 11:15)
        Booking::create([
            'room_id' => $this->room->id,
            'user_id' => $this->user->id,
            'title' => 'First Session',
            'start_time' => '2026-09-10 10:00:00',
            'end_time' => '2026-09-10 11:00:00',
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        // Attempt booking at 11:10 (within 15m buffer window of previous booking)
        $dto = new BookingDTO(
            roomId: $this->room->id,
            userId: $this->user->id,
            title: 'Second Session',
            timeRange: new TimeRange('2026-09-10 11:10:00', '2026-09-10 12:00:00')
        );

        $this->expectException(ScheduleConflictException::class);

        $this->useCase->execute($dto);
    }

    public function test_allows_adjacent_booking_when_buffer_time_satisfied(): void
    {
        // Existing booking 10:00 - 11:00 (buffer 15m -> protected until 11:15)
        Booking::create([
            'room_id' => $this->room->id,
            'user_id' => $this->user->id,
            'title' => 'Morning Workshop',
            'start_time' => '2026-09-10 10:00:00',
            'end_time' => '2026-09-10 11:00:00',
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        // Booking at 11:15 (exact end of buffer) -> ALLOWED
        $dto = new BookingDTO(
            roomId: $this->room->id,
            userId: $this->user->id,
            title: 'Afternoon Workshop',
            timeRange: new TimeRange('2026-09-10 11:15:00', '2026-09-10 12:00:00')
        );

        $booking = $this->useCase->execute($dto);

        $this->assertInstanceOf(Booking::class, $booking);
        $this->assertEquals('Afternoon Workshop', $booking->title);
    }
}
