<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingApiTest extends TestCase
{
    use RefreshDatabase;

    private User $alice;

    private User $bob;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alice = User::factory()->create(['name' => 'Alice']);
        $this->bob = User::factory()->create(['name' => 'Bob']);
        $this->room = Room::factory()->create([
            'name' => 'Meeting Room 101',
            'capacity' => 10,
            'buffer_minutes' => 0,
        ]);
    }

    public function test_booking_requires_user_identification(): void
    {
        $payload = [
            'room_id' => $this->room->id,
            'title' => 'Project Kickoff',
            'start_time' => '2026-09-15T09:00:00Z',
            'end_time' => '2026-09-15T10:00:00Z',
        ];

        // Without X-User-Id header
        $response = $this->postJson('/api/bookings', $payload);

        $response->assertStatus(401)
            ->assertJsonPath('error_code', 'UNAUTHENTICATED');
    }

    public function test_can_create_booking_successfully(): void
    {
        $payload = [
            'room_id' => $this->room->id,
            'title' => 'Sprint Retrospective',
            'start_time' => '2026-09-15T09:00:00Z',
            'end_time' => '2026-09-15T10:30:00Z',
        ];

        $response = $this->withHeader('X-User-Id', (string) $this->alice->id)
            ->postJson('/api/bookings', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Sprint Retrospective')
            ->assertJsonPath('data.room_id', $this->room->id)
            ->assertJsonPath('data.user_id', $this->alice->id)
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.duration_minutes', 90);

        $this->assertDatabaseHas('bookings', [
            'room_id' => $this->room->id,
            'user_id' => $this->alice->id,
            'title' => 'Sprint Retrospective',
            'status' => 'confirmed',
        ]);

        $this->assertDatabaseHas('booking_histories', [
            'user_id' => $this->alice->id,
            'action' => 'created',
        ]);
    }

    public function test_booking_validation_end_time_must_be_after_start_time(): void
    {
        $payload = [
            'room_id' => $this->room->id,
            'title' => 'Invalid Timing',
            'start_time' => '2026-09-15T10:00:00Z',
            'end_time' => '2026-09-15T09:00:00Z', // Before start
        ];

        $response = $this->withHeader('X-User-Id', (string) $this->alice->id)
            ->postJson('/api/bookings', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['end_time']);
    }

    public function test_booking_validation_room_must_exist(): void
    {
        $payload = [
            'room_id' => 999999, // Non existent
            'title' => 'Ghost Room Meeting',
            'start_time' => '2026-09-15T10:00:00Z',
            'end_time' => '2026-09-15T11:00:00Z',
        ];

        $response = $this->withHeader('X-User-Id', (string) $this->alice->id)
            ->postJson('/api/bookings', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['room_id']);
    }

    public function test_double_booking_same_slot_returns_409_conflict(): void
    {
        // First booking by Alice: 10:00 - 11:00
        $this->withHeader('X-User-Id', (string) $this->alice->id)
            ->postJson('/api/bookings', [
                'room_id' => $this->room->id,
                'title' => 'Alice Team Sync',
                'start_time' => '2026-09-15T10:00:00Z',
                'end_time' => '2026-09-15T11:00:00Z',
            ])->assertStatus(201);

        // Second booking by Bob overlapping: 10:30 - 11:30
        $response = $this->withHeader('X-User-Id', (string) $this->bob->id)
            ->postJson('/api/bookings', [
                'room_id' => $this->room->id,
                'title' => 'Bob Client Call',
                'start_time' => '2026-09-15T10:30:00Z',
                'end_time' => '2026-09-15T11:30:00Z',
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('error_code', 'SCHEDULE_CONFLICT')
            ->assertJsonStructure([
                'status',
                'error_code',
                'message',
                'conflict_details' => [
                    'conflicting_booking_id',
                    'title',
                    'start_time',
                    'end_time',
                ],
            ]);
    }

    public function test_adjacent_booking_allowed_when_buffer_is_zero(): void
    {
        // Booking 1: 10:00 - 11:00
        $this->withHeader('X-User-Id', (string) $this->alice->id)
            ->postJson('/api/bookings', [
                'room_id' => $this->room->id,
                'title' => 'Meeting 1',
                'start_time' => '2026-09-15T10:00:00Z',
                'end_time' => '2026-09-15T11:00:00Z',
            ])->assertStatus(201);

        // Booking 2: starts exactly at 11:00 - 12:00 -> MUST BE ALLOWED!
        $response = $this->withHeader('X-User-Id', (string) $this->bob->id)
            ->postJson('/api/bookings', [
                'room_id' => $this->room->id,
                'title' => 'Meeting 2',
                'start_time' => '2026-09-15T11:00:00Z',
                'end_time' => '2026-09-15T12:00:00Z',
            ]);

        $response->assertStatus(201);
    }

    public function test_can_list_bookings_filtered_by_date(): void
    {
        // Booking on Day 1
        Booking::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->alice->id,
            'title' => 'Meeting Day 1',
            'start_time' => '2026-09-15 09:00:00',
            'end_time' => '2026-09-15 10:00:00',
            'status' => 'confirmed',
        ]);

        // Booking on Day 2
        Booking::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->bob->id,
            'title' => 'Meeting Day 2',
            'start_time' => '2026-09-16 09:00:00',
            'end_time' => '2026-09-16 10:00:00',
            'status' => 'confirmed',
        ]);

        // Query day 1 only
        $response = $this->getJson("/api/rooms/{$this->room->id}/bookings?date=2026-09-15");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Meeting Day 1');
    }

    public function test_owner_can_cancel_booking(): void
    {
        $booking = Booking::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->alice->id,
            'status' => 'confirmed',
        ]);

        $response = $this->withHeader('X-User-Id', (string) $this->alice->id)
            ->deleteJson("/api/bookings/{$booking->id}", [
                'reason' => 'Client postponed meeting',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancellation_reason', 'Client postponed meeting');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'cancelled',
            'cancellation_reason' => 'Client postponed meeting',
        ]);

        $this->assertDatabaseHas('booking_histories', [
            'booking_id' => $booking->id,
            'action' => 'cancelled',
            'reason' => 'Client postponed meeting',
        ]);
    }

    public function test_non_owner_cannot_cancel_booking_forbidden(): void
    {
        $booking = Booking::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->alice->id,
            'status' => 'confirmed',
        ]);

        // Bob tries to cancel Alice's booking
        $response = $this->withHeader('X-User-Id', (string) $this->bob->id)
            ->deleteJson("/api/bookings/{$booking->id}");

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'FORBIDDEN');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_owner_can_reschedule_booking(): void
    {
        $booking = Booking::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->alice->id,
            'start_time' => '2026-09-15 10:00:00',
            'end_time' => '2026-09-15 11:00:00',
            'status' => 'confirmed',
        ]);

        $response = $this->withHeader('X-User-Id', (string) $this->alice->id)
            ->postJson("/api/bookings/{$booking->id}/reschedule", [
                'start_time' => '2026-09-15T14:00:00Z',
                'end_time' => '2026-09-15T15:00:00Z',
                'reason' => 'Shifted to afternoon',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.start_time_utc', '2026-09-15T14:00:00+00:00')
            ->assertJsonPath('data.end_time_utc', '2026-09-15T15:00:00+00:00');

        $this->assertDatabaseHas('booking_histories', [
            'booking_id' => $booking->id,
            'action' => 'rescheduled',
            'reason' => 'Shifted to afternoon',
        ]);
    }

    public function test_reschedule_into_conflicting_slot_fails_with_409(): void
    {
        // Existing meeting 14:00 - 15:00
        Booking::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->bob->id,
            'start_time' => '2026-09-15 14:00:00',
            'end_time' => '2026-09-15 15:00:00',
            'status' => 'confirmed',
        ]);

        // Alice has meeting at 10:00 - 11:00
        $aliceBooking = Booking::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->alice->id,
            'start_time' => '2026-09-15 10:00:00',
            'end_time' => '2026-09-15 11:00:00',
            'status' => 'confirmed',
        ]);

        // Alice tries to reschedule into Bob's slot
        $response = $this->withHeader('X-User-Id', (string) $this->alice->id)
            ->postJson("/api/bookings/{$aliceBooking->id}/reschedule", [
                'start_time' => '2026-09-15T14:30:00Z',
                'end_time' => '2026-09-15T15:30:00Z',
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('error_code', 'SCHEDULE_CONFLICT');
    }

    public function test_can_view_booking_audit_trail_history(): void
    {
        $booking = Booking::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->alice->id,
            'start_time' => '2026-09-15 10:00:00',
            'end_time' => '2026-09-15 11:00:00',
            'status' => 'confirmed',
        ]);

        // Reschedule it once
        $this->withHeader('X-User-Id', (string) $this->alice->id)
            ->postJson("/api/bookings/{$booking->id}/reschedule", [
                'start_time' => '2026-09-15T13:00:00Z',
                'end_time' => '2026-09-15T14:00:00Z',
                'reason' => 'First reschedule',
            ]);

        // Cancel it
        $this->withHeader('X-User-Id', (string) $this->alice->id)
            ->deleteJson("/api/bookings/{$booking->id}", [
                'reason' => 'Cancelled eventually',
            ]);

        // Query history
        $response = $this->getJson("/api/bookings/{$booking->id}/history");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.action', 'cancelled')
            ->assertJsonPath('data.1.action', 'rescheduled');
    }

    public function test_explicit_timezone_conversion_on_response(): void
    {
        $booking = Booking::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->alice->id,
            'start_time' => '2026-09-15 03:00:00', // 03:00 UTC == 10:00 Asia/Jakarta (+07:00)
            'end_time' => '2026-09-15 04:00:00',
            'status' => 'confirmed',
        ]);

        $response = $this->getJson("/api/bookings/{$booking->id}?timezone=Asia/Jakarta");

        $response->assertStatus(200)
            ->assertJsonPath('data.start_time_utc', '2026-09-15T03:00:00+00:00')
            ->assertJsonPath('data.start_time_local', '2026-09-15T10:00:00+07:00')
            ->assertJsonPath('data.timezone', 'Asia/Jakarta');
    }

    public function test_can_query_occupied_slots_for_room(): void
    {
        Booking::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->alice->id,
            'title' => 'Morning Strategy',
            'start_time' => '2026-09-15 09:00:00',
            'end_time' => '2026-09-15 10:30:00',
            'status' => 'confirmed',
        ]);

        $response = $this->getJson("/api/rooms/{$this->room->id}/occupied-slots?date=2026-09-15");

        $response->assertStatus(200)
            ->assertJsonPath('room_id', $this->room->id)
            ->assertJsonPath('date', '2026-09-15')
            ->assertJsonCount(1, 'occupied_slots')
            ->assertJsonPath('occupied_slots.0.title', 'Morning Strategy')
            ->assertJsonPath('occupied_slots.0.start_time_local', '09:00')
            ->assertJsonPath('occupied_slots.0.end_time_local', '10:30');
    }
}
