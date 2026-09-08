<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Booking::where('title', 'like', 'Concurrent Meeting%')->delete();
        Room::where('name', 'High Concurrency Hall')->delete();
        User::where('email', 'like', 'concurrent_%@example.com')->delete();
    }

    protected function tearDown(): void
    {
        Booking::where('title', 'like', 'Concurrent Meeting%')->delete();
        Room::where('name', 'High Concurrency Hall')->delete();
        User::where('email', 'like', 'concurrent_%@example.com')->delete();

        parent::tearDown();
    }

    /**
     * Proves Enhancement requirement:
     * Safe against race conditions. Two simultaneous booking requests on the exact
     * same slot will result in exactly ONE confirmed booking and ONE conflict rejection.
     */
    public function test_concurrent_booking_requests_produce_only_one_booking(): void
    {
        $user1 = User::factory()->create(['name' => 'Concurrent User 1', 'email' => 'concurrent_1@example.com']);
        $user2 = User::factory()->create(['name' => 'Concurrent User 2', 'email' => 'concurrent_2@example.com']);
        $room = Room::factory()->create(['name' => 'High Concurrency Hall', 'is_active' => true, 'buffer_minutes' => 0]);

        $start = '2026-09-25 14:00:00';
        $end = '2026-09-25 15:00:00';

        $phpBinary = PHP_BINARY;
        $artisanPath = base_path('artisan');

        // Disconnect parent connection to ensure all locks are committed and released before child processes start
        DB::disconnect();

        $cmd1 = "\"{$phpBinary}\" \"{$artisanPath}\" booking:attempt {$room->id} {$user1->id} \"{$start}\" \"{$end}\" \"Concurrent Meeting 1\" --env=testing";
        $cmd2 = "\"{$phpBinary}\" \"{$artisanPath}\" booking:attempt {$room->id} {$user2->id} \"{$start}\" \"{$end}\" \"Concurrent Meeting 2\" --env=testing";

        // Launch both processes concurrently
        $p1 = popen($cmd1, 'r');
        $p2 = popen($cmd2, 'r');

        $out1 = stream_get_contents($p1);
        $out2 = stream_get_contents($p2);

        $exit1 = pclose($p1);
        $exit2 = pclose($p2);

        $combinedOutput = "Proc 1 (exit {$exit1}): {$out1}\nProc 2 (exit {$exit2}): {$out2}\n";

        // Reconnect parent database connection for assertions
        DB::reconnect();

        $exitCodes = [$exit1, $exit2];
        $successCount = count(array_filter($exitCodes, fn ($code) => $code === 0));
        $conflictCount = count(array_filter($exitCodes, fn ($code) => $code === 10 || $code === 1));

        $this->assertSame(1, $successCount, "Exactly one booking must succeed. Details:\n{$combinedOutput}");
        $this->assertSame(1, $conflictCount, "Exactly one booking must fail with conflict. Details:\n{$combinedOutput}");

        // Confirm database strictly holds exactly 1 booking
        $dbCount = Booking::where('room_id', $room->id)->where('status', 'confirmed')->count();
        $this->assertSame(1, $dbCount, 'Database must have strictly 1 confirmed booking for this room.');
    }
}
