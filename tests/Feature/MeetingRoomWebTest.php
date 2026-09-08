<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetingRoomWebTest extends TestCase
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
            'name' => 'Ruang Garuda',
            'capacity' => 15,
            'location' => 'Lantai 2',
            'buffer_minutes' => 0,
            'is_active' => true,
        ]);
    }

    public function test_home_redirects_to_rooms_index(): void
    {
        $response = $this->get('/');
        $response->assertRedirect('/rooms');
    }

    public function test_rooms_index_renders_successfully(): void
    {
        $response = $this->get('/rooms');

        $response->assertStatus(200)
            ->assertSee('Daftar Ruang Meeting')
            ->assertSee('Ruang Garuda')
            ->assertSee('15 Orang');
    }

    public function test_can_create_room_via_web_form(): void
    {
        $payload = [
            'name' => 'Ruang Merapi',
            'capacity' => 20,
            'location' => 'Lantai 3',
            'buffer_minutes' => 15,
            'is_active' => '1',
        ];

        $response = $this->post('/rooms', $payload);

        $response->assertRedirect('/rooms');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('rooms', [
            'name' => 'Ruang Merapi',
            'capacity' => 20,
        ]);
    }

    public function test_room_show_renders_booking_form_and_schedule(): void
    {
        Booking::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->alice->id,
            'title' => 'Diskusi Strategis',
            'start_time' => '2026-09-15 09:00:00',
            'end_time' => '2026-09-15 10:00:00',
            'status' => 'confirmed',
        ]);

        $response = $this->get("/rooms/{$this->room->id}");

        $response->assertStatus(200)
            ->assertSee('Ruang Garuda')
            ->assertSee('Diskusi Strategis')
            ->assertSee('Reservasi Ruang Ini');
    }

    public function test_can_create_booking_via_web(): void
    {
        $payload = [
            'room_id' => $this->room->id,
            'title' => 'Weekly Sync Up',
            'start_time' => '2026-09-15T14:00',
            'end_time' => '2026-09-15T15:00',
        ];

        $response = $this->withSession(['active_user_id' => $this->alice->id])
            ->post('/bookings', $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('bookings', [
            'room_id' => $this->room->id,
            'user_id' => $this->alice->id,
            'title' => 'Weekly Sync Up',
        ]);

        $this->assertSame(1, Booking::where('room_id', $this->room->id)->count());
    }

    public function test_single_booking_with_explicit_zero_recurring_creates_only_one_slot(): void
    {
        $payload = [
            'room_id' => $this->room->id,
            'title' => 'One Shot 2 Hour Meeting',
            'is_recurring' => '0',
            'start_time' => '2026-09-18T10:00',
            'end_time' => '2026-09-18T12:00',
        ];

        $response = $this->withSession(['active_user_id' => $this->alice->id])
            ->post('/bookings', $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertSame(1, Booking::where('title', 'One Shot 2 Hour Meeting')->count());
    }

    public function test_double_booking_via_web_flashes_conflict_error(): void
    {
        // First booking
        Booking::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->alice->id,
            'start_time' => '2026-09-15 10:00:00',
            'end_time' => '2026-09-15 11:00:00',
            'status' => 'confirmed',
        ]);

        // Attempt overlapping booking: 10:30 - 11:30
        $response = $this->withSession(['active_user_id' => $this->bob->id])
            ->post('/bookings', [
                'room_id' => $this->room->id,
                'title' => 'Bob Tabrakan Meeting',
                'start_time' => '2026-09-15T10:30',
                'end_time' => '2026-09-15T11:30',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('conflict_error');
    }

    public function test_owner_can_cancel_booking_via_web(): void
    {
        $booking = Booking::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->alice->id,
            'status' => 'confirmed',
        ]);

        $response = $this->withSession(['active_user_id' => $this->alice->id])
            ->post("/bookings/{$booking->id}/cancel", [
                'reason' => 'Dibatalkan oleh Alice via web',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_non_owner_cannot_cancel_booking_via_web(): void
    {
        $booking = Booking::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->alice->id,
            'status' => 'confirmed',
        ]);

        // Bob tries to cancel Alice's booking
        $response = $this->withSession(['active_user_id' => $this->bob->id])
            ->post("/bookings/{$booking->id}/cancel");

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_can_switch_user_via_web(): void
    {
        $response = $this->post('/switch-user', [
            'user_id' => $this->bob->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('active_user_id', $this->bob->id);
    }

    public function test_search_available_rooms_view(): void
    {
        $response = $this->get('/rooms/search?'.http_build_query([
            'start_time' => '2026-09-15T09:00',
            'end_time' => '2026-09-15T10:00',
            'min_capacity' => 10,
        ]));

        $response->assertStatus(200)
            ->assertSee('Pencarian Ruang Tersedia')
            ->assertSee('Ruang Garuda');
    }

    public function test_search_available_rooms_with_empty_min_capacity_does_not_force_zero(): void
    {
        // When user submits with empty min_capacity query parameter
        $response = $this->get('/rooms/search?start_time=2026-09-15T09:00&end_time=2026-09-15T10:00&min_capacity=');

        $response->assertStatus(200)
            ->assertSee('Ruang Garuda')
            ->assertDontSee('value="0"');
    }

    public function test_search_available_rooms_with_invalid_range_flashes_error(): void
    {
        // End time before start time
        $response = $this->get('/rooms/search?start_time=2026-09-15T10:00&end_time=2026-09-15T09:00');

        $response->assertStatus(200)
            ->assertSessionHas('error');
    }

    public function test_search_available_rooms_excludes_room_on_unconfigured_closed_day(): void
    {
        // Configure operating hours only on Tuesday (day 2)
        $this->room->operatingHours()->create([
            'day_of_week' => 2,
            'open_time' => '08:00:00',
            'close_time' => '17:00:00',
        ]);

        // Search on Wednesday 2026-09-16 (day 3 - room is closed)
        $response = $this->get('/rooms/search?start_time=2026-09-16T09:00&end_time=2026-09-16T10:00');

        $response->assertStatus(200)
            ->assertDontSee('Ruang Garuda');
    }

    public function test_room_show_prefills_booking_times_from_search_query_parameters(): void
    {
        $response = $this->get("/rooms/{$this->room->id}?start_time=2026-09-15T14:30&end_time=2026-09-15T16:00");

        $response->assertStatus(200)
            ->assertSee('value="2026-09-15T14:30"', false)
            ->assertSee('value="2026-09-15T16:00"', false);
    }

    public function test_multi_day_search_from_date_1_to_5_at_11_to_12_ignores_unrelated_booking_at_14_to_15(): void
    {
        // Room 1 (Ruang Garuda): Has an existing booking on Day 4 (2026-09-04) from 14:00 to 15:00
        Booking::create([
            'room_id' => $this->room->id,
            'user_id' => $this->alice->id,
            'title' => 'Afternoon Session',
            'start_time' => '2026-09-04 14:00:00',
            'end_time' => '2026-09-04 15:00:00',
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        // Room 2 (Ruang Rinjani): Has an overlapping booking on Day 4 (2026-09-04) from 11:30 to 12:30
        $roomB = Room::factory()->create([
            'name' => 'Ruang Rinjani',
            'capacity' => 20,
            'is_active' => true,
            'buffer_minutes' => 0,
        ]);
        Booking::create([
            'room_id' => $roomB->id,
            'user_id' => $this->alice->id,
            'title' => 'Direct Conflict Session',
            'start_time' => '2026-09-04 11:30:00',
            'end_time' => '2026-09-04 12:30:00',
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        // Case A: User searches using recurring parameters (date 1 to 5, jam 11-12)
        $responseRecurring = $this->get('/rooms/search?'.http_build_query([
            'search_type' => 'recurring',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-05',
            'start_time_of_day' => '11:00',
            'end_time_of_day' => '12:00',
            'frequency' => 'daily',
        ]));

        $responseRecurring->assertStatus(200)
            ->assertSee('Ruang Garuda')   // Must appear because 11:00-12:00 does not collide with 14:00-15:00
            ->assertDontSee('Ruang Rinjani'); // Must NOT appear because 11:30-12:30 collides

        // Case B: User enters multi-day range in standard start_time and end_time inputs
        $responseSmart = $this->get('/rooms/search?'.http_build_query([
            'start_time' => '2026-09-01T11:00',
            'end_time' => '2026-09-05T12:00',
        ]));

        $responseSmart->assertStatus(200)
            ->assertSee('Ruang Garuda')
            ->assertDontSee('Ruang Rinjani');
    }

    public function test_can_update_room_and_toggle_active_status_via_web(): void
    {
        $response = $this->post("/rooms/{$this->room->id}/update", [
            'name' => 'Ruang Garuda Modern',
            'capacity' => 25,
            'location' => 'Lantai 4',
            'buffer_minutes' => 10,
            'is_active' => 0,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('rooms', [
            'id' => $this->room->id,
            'name' => 'Ruang Garuda Modern',
            'capacity' => 25,
            'is_active' => false,
        ]);
    }

    public function test_can_set_dynamic_operating_hours_via_web(): void
    {
        $response = $this->post("/rooms/{$this->room->id}/operating-hours", [
            'hours' => [
                ['day_of_week' => 1, 'open_time' => '08:00', 'close_time' => '17:00'],
                ['day_of_week' => 6, 'open_time' => '09:00', 'close_time' => '14:00'], // Sabtu
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('room_operating_hours', [
            'room_id' => $this->room->id,
            'day_of_week' => 6,
            'open_time' => '09:00:00',
            'close_time' => '14:00:00',
        ]);
    }

    public function test_can_clear_operating_hours_for_24_hours_via_web(): void
    {
        $response = $this->post("/rooms/{$this->room->id}/operating-hours", [
            'hours' => [],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('room_operating_hours', [
            'room_id' => $this->room->id,
        ]);
    }

    public function test_can_delete_room_via_web(): void
    {
        $response = $this->delete("/rooms/{$this->room->id}");

        $response->assertRedirect('/rooms');
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('rooms', [
            'id' => $this->room->id,
        ]);
    }

    public function test_my_bookings_page_renders_and_shows_active_user_bookings(): void
    {
        $aliceBooking = Booking::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->alice->id,
            'title' => 'Diskusi Alice Penting',
            'start_time' => now()->addDays(2)->setHour(10)->setMinute(0),
            'end_time' => now()->addDays(2)->setHour(11)->setMinute(0),
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        $bobBooking = Booking::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->bob->id,
            'title' => 'Diskusi Rahasia Bob',
            'start_time' => now()->addDays(3)->setHour(10)->setMinute(0),
            'end_time' => now()->addDays(3)->setHour(11)->setMinute(0),
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        $response = $this->withSession(['active_user_id' => $this->alice->id])
            ->get('/my-bookings');

        $response->assertStatus(200)
            ->assertSee('Reservasi Saya')
            ->assertSee('Diskusi Alice Penting')
            ->assertDontSee('Diskusi Rahasia Bob');
    }

    public function test_my_bookings_page_filters_by_status(): void
    {
        Booking::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->alice->id,
            'title' => 'Booking Alice Terkonfirmasi',
            'start_time' => now()->addDays(1)->setHour(14)->setMinute(0),
            'end_time' => now()->addDays(1)->setHour(15)->setMinute(0),
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        Booking::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->alice->id,
            'title' => 'Booking Alice Dibatalkan',
            'start_time' => now()->addDays(2)->setHour(14)->setMinute(0),
            'end_time' => now()->addDays(2)->setHour(15)->setMinute(0),
            'status' => Booking::STATUS_CANCELLED,
            'cancellation_reason' => 'Meeting ditunda',
        ]);

        // Filter confirmed
        $responseConfirmed = $this->withSession(['active_user_id' => $this->alice->id])
            ->get('/my-bookings?status=confirmed');

        $responseConfirmed->assertStatus(200)
            ->assertSee('Booking Alice Terkonfirmasi')
            ->assertDontSee('Booking Alice Dibatalkan');

        // Filter cancelled
        $responseCancelled = $this->withSession(['active_user_id' => $this->alice->id])
            ->get('/my-bookings?status=cancelled');

        $responseCancelled->assertStatus(200)
            ->assertSee('Booking Alice Dibatalkan')
            ->assertSee('Meeting ditunda')
            ->assertDontSee('Booking Alice Terkonfirmasi');
    }

    public function test_switching_user_updates_my_bookings_page(): void
    {
        Booking::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->alice->id,
            'title' => 'Pemesanan Alice',
            'start_time' => now()->addDays(1)->setHour(9)->setMinute(0),
            'end_time' => now()->addDays(1)->setHour(10)->setMinute(0),
        ]);

        Booking::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->bob->id,
            'title' => 'Pemesanan Bob',
            'start_time' => now()->addDays(1)->setHour(10)->setMinute(0),
            'end_time' => now()->addDays(1)->setHour(11)->setMinute(0),
        ]);

        // Switch user to Bob
        $switchResponse = $this->post('/switch-user', ['user_id' => $this->bob->id]);
        $switchResponse->assertRedirect();

        // Check my-bookings as Bob
        $myBookingsResponse = $this->withSession(['active_user_id' => $this->bob->id])
            ->get('/my-bookings');

        $myBookingsResponse->assertStatus(200)
            ->assertSee('Pemesanan Bob')
            ->assertDontSee('Pemesanan Alice');
    }

    public function test_room_show_displays_tab_for_my_bookings(): void
    {
        Booking::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->alice->id,
            'title' => 'Booking Alice di Garuda',
            'start_time' => now()->addDays(1)->setHour(9)->setMinute(0),
            'end_time' => now()->addDays(1)->setHour(10)->setMinute(0),
        ]);

        $response = $this->withSession(['active_user_id' => $this->alice->id])
            ->get("/rooms/{$this->room->id}");

        $response->assertStatus(200)
            ->assertSee('Booking Saya (1)')
            ->assertSee('Semua (1)');
    }
}
