<?php

namespace Tests\Unit\UseCases;

use App\Domain\Booking\UseCases\SetOperatingHoursUseCase;
use App\Models\Room;
use App\Models\RoomOperatingHour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetOperatingHoursUseCaseTest extends TestCase
{
    use RefreshDatabase;

    private SetOperatingHoursUseCase $useCase;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useCase = new SetOperatingHoursUseCase;
        $this->room = Room::factory()->create();
    }

    public function test_can_set_flexible_operating_hours_including_weekends(): void
    {
        $hours = [
            ['day_of_week' => 1, 'open_time' => '08:00', 'close_time' => '17:00'], // Senin
            ['day_of_week' => 5, 'open_time' => '09:00', 'close_time' => '16:00'], // Jumat
            ['day_of_week' => 6, 'open_time' => '10:00', 'close_time' => '15:00'], // Sabtu (weekend)
        ];

        $result = $this->useCase->execute($this->room, $hours);

        $this->assertCount(3, $result);
        $this->assertDatabaseHas('room_operating_hours', [
            'room_id' => $this->room->id,
            'day_of_week' => 6,
            'open_time' => '10:00:00',
            'close_time' => '15:00:00',
        ]);
    }

    public function test_can_clear_all_operating_hours_for_24_hour_operation(): void
    {
        // Setup initial operating hours
        RoomOperatingHour::create([
            'room_id' => $this->room->id,
            'day_of_week' => 1,
            'open_time' => '08:00:00',
            'close_time' => '18:00:00',
        ]);

        $this->assertDatabaseCount('room_operating_hours', 1);

        // Clear all hours (empty array)
        $result = $this->useCase->execute($this->room, []);

        $this->assertCount(0, $result);
        $this->assertDatabaseCount('room_operating_hours', 0);
    }

    public function test_can_add_and_remove_days_dynamically(): void
    {
        // Start with Mon & Tue
        $this->useCase->execute($this->room, [
            ['day_of_week' => 1, 'open_time' => '08:00', 'close_time' => '17:00'],
            ['day_of_week' => 2, 'open_time' => '08:00', 'close_time' => '17:00'],
        ]);
        $this->assertDatabaseCount('room_operating_hours', 2);

        // Update: remove Tue (2), keep Mon (1), and add Wed (3) & Thu (4)
        $this->useCase->execute($this->room, [
            ['day_of_week' => 1, 'open_time' => '08:00', 'close_time' => '17:00'],
            ['day_of_week' => 3, 'open_time' => '08:00', 'close_time' => '17:00'],
            ['day_of_week' => 4, 'open_time' => '08:00', 'close_time' => '17:00'],
        ]);

        $this->assertDatabaseCount('room_operating_hours', 3);
        $this->assertDatabaseMissing('room_operating_hours', [
            'room_id' => $this->room->id,
            'day_of_week' => 2,
        ]);
        $this->assertDatabaseHas('room_operating_hours', [
            'room_id' => $this->room->id,
            'day_of_week' => 3,
        ]);
    }
}
