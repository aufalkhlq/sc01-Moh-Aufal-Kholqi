<?php

namespace Tests\Feature;

use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_rooms(): void
    {
        Room::factory()->create(['name' => 'Room A', 'capacity' => 10, 'location' => 'Building 1']);
        Room::factory()->create(['name' => 'Room B', 'capacity' => 20, 'location' => 'Building 2']);

        $response = $this->getJson('/api/rooms');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'capacity', 'location', 'buffer_minutes', 'is_active', 'created_at'],
                ],
            ]);
    }

    public function test_can_filter_rooms_by_capacity(): void
    {
        Room::factory()->create(['name' => 'Small Room', 'capacity' => 4, 'location' => 'Floor 1']);
        Room::factory()->create(['name' => 'Large Room', 'capacity' => 30, 'location' => 'Floor 2']);

        $response = $this->getJson('/api/rooms?min_capacity=15');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Large Room');
    }

    public function test_can_create_room_with_valid_attributes(): void
    {
        $payload = [
            'name' => 'Boardroom Executive',
            'capacity' => 16,
            'location' => 'Floor 5, Suite A',
            'buffer_minutes' => 15,
            'is_active' => true,
        ];

        $response = $this->postJson('/api/rooms', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Boardroom Executive')
            ->assertJsonPath('data.capacity', 16)
            ->assertJsonPath('data.buffer_minutes', 15);

        $this->assertDatabaseHas('rooms', [
            'name' => 'Boardroom Executive',
            'capacity' => 16,
        ]);
    }

    public function test_create_room_fails_on_missing_fields(): void
    {
        $response = $this->postJson('/api/rooms', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'capacity', 'location']);
    }

    public function test_can_update_room(): void
    {
        $room = Room::factory()->create(['name' => 'Old Room', 'capacity' => 8]);

        $response = $this->putJson("/api/rooms/{$room->id}", [
            'name' => 'Renovated Room',
            'capacity' => 12,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Renovated Room')
            ->assertJsonPath('data.capacity', 12);

        $this->assertDatabaseHas('rooms', [
            'id' => $room->id,
            'name' => 'Renovated Room',
            'capacity' => 12,
        ]);
    }

    public function test_can_delete_room(): void
    {
        $room = Room::factory()->create();

        $response = $this->deleteJson("/api/rooms/{$room->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('rooms', ['id' => $room->id]);
    }

    public function test_can_configure_room_operating_hours(): void
    {
        $room = Room::factory()->create();

        $payload = [
            'hours' => [
                ['day_of_week' => 1, 'open_time' => '08:00:00', 'close_time' => '17:00:00'],
                ['day_of_week' => 2, 'open_time' => '08:00:00', 'close_time' => '17:00:00'],
            ],
        ];

        $response = $this->postJson("/api/rooms/{$room->id}/operating-hours", $payload);

        $response->assertStatus(200);
        $this->assertDatabaseHas('room_operating_hours', [
            'room_id' => $room->id,
            'day_of_week' => 1,
            'open_time' => '08:00:00',
            'close_time' => '17:00:00',
        ]);
    }
}
