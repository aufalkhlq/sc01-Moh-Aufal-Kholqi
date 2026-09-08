<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StorageLevelConstraintTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Proves Nice-to-Have requirement:
     * Overlap prevention is enforced directly by the database storage layer (via SQL triggers),
     * completely independent of any application code or service layer logic.
     */
    public function test_raw_database_insert_violating_overlap_is_blocked_by_storage_trigger(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create();

        // 1. Insert first booking directly using raw DB query (bypassing all PHP application code)
        DB::table('bookings')->insert([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'title' => 'Direct DB Booking 1',
            'start_time' => '2026-09-15 10:00:00',
            'end_time' => '2026-09-15 11:00:00',
            'status' => 'confirmed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseCount('bookings', 1);

        // 2. Attempt to insert overlapping booking directly using raw DB query
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('DB_STORAGE_CONSTRAINT');

        DB::table('bookings')->insert([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'title' => 'Bypassing Application Code Overlap Attempt',
            'start_time' => '2026-09-15 10:30:00',
            'end_time' => '2026-09-15 11:30:00',
            'status' => 'confirmed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_raw_database_insert_adjacent_booking_succeeds_in_storage(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create();

        // Insert first booking: 10:00 - 11:00
        DB::table('bookings')->insert([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'title' => 'Direct DB Booking 1',
            'start_time' => '2026-09-15 10:00:00',
            'end_time' => '2026-09-15 11:00:00',
            'status' => 'confirmed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert adjacent booking: 11:00 - 12:00 directly in DB
        DB::table('bookings')->insert([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'title' => 'Adjacent DB Booking 2',
            'start_time' => '2026-09-15 11:00:00',
            'end_time' => '2026-09-15 12:00:00',
            'status' => 'confirmed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseCount('bookings', 2);
    }
}
