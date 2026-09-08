<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomOperatingHour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnhancementFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->room = Room::factory()->create([
            'name' => 'Innovation Lab',
            'capacity' => 15,
            'buffer_minutes' => 0,
        ]);
    }

    public function test_can_create_recurring_daily_bookings_with_exception_dates(): void
    {
        $payload = [
            'room_id' => $this->room->id,
            'title' => 'Daily Standup Series',
            'is_recurring' => true,
            'frequency' => 'daily',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-05', // 5 days total: 1, 2, 3, 4, 5
            'start_time_of_day' => '09:00:00',
            'end_time_of_day' => '09:30:00',
            'exception_dates' => ['2026-09-03'], // Skip September 3
            'timezone' => 'UTC',
        ];

        $response = $this->withHeader('X-User-Id', (string) $this->user->id)
            ->postJson('/api/bookings', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('count', 4); // 5 days minus 1 skipped day = 4 bookings

        $this->assertDatabaseCount('bookings', 4);
        $this->assertDatabaseHas('bookings', [
            'start_time' => '2026-09-01 09:00:00',
        ]);
        $this->assertDatabaseHas('bookings', [
            'start_time' => '2026-09-02 09:00:00',
        ]);
        // Sept 3 must NOT exist
        $this->assertDatabaseMissing('bookings', [
            'start_time' => '2026-09-03 09:00:00',
        ]);
        $this->assertDatabaseHas('bookings', [
            'start_time' => '2026-09-04 09:00:00',
        ]);
        $this->assertDatabaseHas('bookings', [
            'start_time' => '2026-09-05 09:00:00',
        ]);
    }

    public function test_operating_hours_enforcement_blocks_out_of_hours_bookings(): void
    {
        // Configure operating hours for Room: Tuesday (day 2) from 08:00:00 to 17:00:00
        // 2026-09-15 is a Tuesday (dayOfWeek = 2)
        RoomOperatingHour::create([
            'room_id' => $this->room->id,
            'day_of_week' => 2,
            'open_time' => '08:00:00',
            'close_time' => '17:00:00',
        ]);

        // Attempt booking at night: 19:00 - 20:00
        $response = $this->withHeader('X-User-Id', (string) $this->user->id)
            ->postJson('/api/bookings', [
                'room_id' => $this->room->id,
                'title' => 'Late Night Coding',
                'start_time' => '2026-09-15T19:00:00Z',
                'end_time' => '2026-09-15T20:00:00Z',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'OUTSIDE_OPERATING_HOURS');

        // Booking within 09:00 - 10:00 succeeds
        $validResponse = $this->withHeader('X-User-Id', (string) $this->user->id)
            ->postJson('/api/bookings', [
                'room_id' => $this->room->id,
                'title' => 'Morning Planning',
                'start_time' => '2026-09-15T09:00:00Z',
                'end_time' => '2026-09-15T10:00:00Z',
            ]);

        $validResponse->assertStatus(201);
    }

    public function test_buffer_minutes_enforcement(): void
    {
        // Room with 15 minutes buffer
        $roomWithBuffer = Room::factory()->create([
            'name' => 'Cleanroom Alpha',
            'buffer_minutes' => 15,
        ]);

        // Booking 1: 10:00 - 11:00
        $this->withHeader('X-User-Id', (string) $this->user->id)
            ->postJson('/api/bookings', [
                'room_id' => $roomWithBuffer->id,
                'title' => 'First Session',
                'start_time' => '2026-09-15T10:00:00Z',
                'end_time' => '2026-09-15T11:00:00Z',
            ])->assertStatus(201);

        // Booking 2: 11:00 - 12:00 (Starts during 15-minute buffer interval!) -> CONFLICT
        $conflictResponse = $this->withHeader('X-User-Id', (string) $this->user->id)
            ->postJson('/api/bookings', [
                'room_id' => $roomWithBuffer->id,
                'title' => 'Too Soon Session',
                'start_time' => '2026-09-15T11:00:00Z',
                'end_time' => '2026-09-15T12:00:00Z',
            ]);

        $conflictResponse->assertStatus(409)
            ->assertJsonPath('error_code', 'SCHEDULE_CONFLICT');

        // Booking 3: 11:15 - 12:15 (Starts after 15-minute buffer interval) -> SUCCESS
        $allowedResponse = $this->withHeader('X-User-Id', (string) $this->user->id)
            ->postJson('/api/bookings', [
                'room_id' => $roomWithBuffer->id,
                'title' => 'Respectful Session',
                'start_time' => '2026-09-15T11:15:00Z',
                'end_time' => '2026-09-15T12:15:00Z',
            ]);

        $allowedResponse->assertStatus(201);
    }

    public function test_availability_search_filters_by_capacity_and_schedules(): void
    {
        // Room 1: Capacity 20, Free
        $availableRoom = Room::factory()->create([
            'name' => 'Available Hall',
            'capacity' => 20,
            'is_active' => true,
        ]);

        // Room 2: Capacity 25, but Booked
        $bookedRoom = Room::factory()->create([
            'name' => 'Busy Hall',
            'capacity' => 25,
            'is_active' => true,
        ]);
        Booking::factory()->create([
            'room_id' => $bookedRoom->id,
            'user_id' => $this->user->id,
            'start_time' => '2026-09-15 10:00:00',
            'end_time' => '2026-09-15 11:00:00',
            'status' => 'confirmed',
        ]);

        // Room 3: Free, but Small Capacity (5)
        Room::factory()->create([
            'name' => 'Tiny Booth',
            'capacity' => 5,
            'is_active' => true,
        ]);

        // Search for slots between 10:00 - 11:00 with min_capacity = 18
        $response = $this->getJson('/api/rooms/available?'.http_build_query([
            'start_time' => '2026-09-15T10:00:00Z',
            'end_time' => '2026-09-15T11:00:00Z',
            'min_capacity' => 18,
        ]));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $availableRoom->id)
            ->assertJsonPath('data.0.name', 'Available Hall');
    }

    public function test_recurring_availability_search_via_api_ignores_non_colliding_booking_on_same_day(): void
    {
        // Room 1 (Innovation Lab): Booking on Sept 4 from 14:00 to 15:00
        Booking::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->user->id,
            'start_time' => '2026-09-04 14:00:00',
            'end_time' => '2026-09-04 15:00:00',
            'status' => 'confirmed',
        ]);

        // Room 2: Booking on Sept 4 from 11:30 to 12:30 (Collides with 11:00 - 12:00)
        $collidingRoom = Room::factory()->create([
            'name' => 'Colliding Room',
            'capacity' => 15,
            'buffer_minutes' => 0,
        ]);
        Booking::factory()->create([
            'room_id' => $collidingRoom->id,
            'user_id' => $this->user->id,
            'start_time' => '2026-09-04 11:30:00',
            'end_time' => '2026-09-04 12:30:00',
            'status' => 'confirmed',
        ]);

        // Search recurring from 2026-09-01 to 2026-09-05, jam 11:00 to 12:00
        $response = $this->getJson('/api/rooms/available?'.http_build_query([
            'search_type' => 'recurring',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-05',
            'start_time_of_day' => '11:00',
            'end_time_of_day' => '12:00',
            'frequency' => 'daily',
            'min_capacity' => 10,
        ]));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->room->id)
            ->assertJsonPath('data.0.name', 'Innovation Lab');
    }

    public function test_can_get_my_bookings_via_api(): void
    {
        $otherUser = User::factory()->create();

        // 2 bookings for $this->user
        $myBooking1 = Booking::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->user->id,
            'title' => 'My Active Booking',
            'start_time' => '2026-10-01 10:00:00',
            'end_time' => '2026-10-01 11:00:00',
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        $myBooking2 = Booking::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->user->id,
            'title' => 'My Cancelled Booking',
            'start_time' => '2026-10-02 10:00:00',
            'end_time' => '2026-10-02 11:00:00',
            'status' => Booking::STATUS_CANCELLED,
        ]);

        // 1 booking for $otherUser
        Booking::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $otherUser->id,
            'title' => 'Other User Booking',
            'start_time' => '2026-10-03 10:00:00',
            'end_time' => '2026-10-03 11:00:00',
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        // All my bookings
        $response = $this->withHeader('X-User-Id', (string) $this->user->id)
            ->getJson('/api/bookings/my');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');

        // Filter status=confirmed
        $confirmedResponse = $this->withHeader('X-User-Id', (string) $this->user->id)
            ->getJson('/api/bookings/my?status=confirmed');

        $confirmedResponse->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'My Active Booking');

        // Filter status=cancelled
        $cancelledResponse = $this->withHeader('X-User-Id', (string) $this->user->id)
            ->getJson('/api/bookings/my?status=cancelled');

        $cancelledResponse->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'My Cancelled Booking');
    }
}
