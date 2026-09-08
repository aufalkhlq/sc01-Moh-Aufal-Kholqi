<?php

namespace Tests\Unit\UseCases;

use App\Domain\Booking\UseCases\UpdateRoomUseCase;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateRoomUseCaseTest extends TestCase
{
    use RefreshDatabase;

    private UpdateRoomUseCase $useCase;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useCase = new UpdateRoomUseCase;
        $this->room = Room::factory()->create([
            'name' => 'Ruang Merbabu',
            'capacity' => 8,
            'is_active' => true,
        ]);
    }

    public function test_can_update_room_details(): void
    {
        $updated = $this->useCase->execute($this->room, [
            'name' => 'Ruang Merbabu VIP',
            'capacity' => 15,
            'location' => 'Lantai 3',
            'buffer_minutes' => 20,
        ]);

        $this->assertEquals('Ruang Merbabu VIP', $updated->name);
        $this->assertEquals(15, $updated->capacity);
        $this->assertEquals('Lantai 3', $updated->location);
        $this->assertEquals(20, $updated->buffer_minutes);
    }

    public function test_can_toggle_room_active_status(): void
    {
        // Set inactive
        $inactive = $this->useCase->execute($this->room, [
            'is_active' => false,
        ]);
        $this->assertFalse($inactive->is_active);
        $this->assertDatabaseHas('rooms', [
            'id' => $this->room->id,
            'is_active' => false,
        ]);

        // Set active again
        $active = $this->useCase->execute($this->room, [
            'is_active' => true,
        ]);
        $this->assertTrue($active->is_active);
        $this->assertDatabaseHas('rooms', [
            'id' => $this->room->id,
            'is_active' => true,
        ]);
    }
}
