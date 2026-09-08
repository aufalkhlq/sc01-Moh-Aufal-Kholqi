<?php

namespace Database\Seeders;

use App\Models\Room;
use App\Models\RoomOperatingHour;
use App\Models\User;
use Illuminate\Database\Seeder;

class MeetingRoomSeeder extends Seeder
{
    public function run(): void
    {
        // Seed standard users
        $alice = User::firstOrCreate(
            ['email' => 'alice@example.com'],
            ['name' => 'Alice Margatroid', 'password' => bcrypt('password123')]
        );

        $bob = User::firstOrCreate(
            ['email' => 'bob@example.com'],
            ['name' => 'Bob Smith', 'password' => bcrypt('password123')]
        );

        // Seed meeting rooms
        $rinjani = Room::firstOrCreate(
            ['name' => 'Ruang Rinjani'],
            [
                'capacity' => 12,
                'location' => 'Lantai 2, Sayap Barat',
                'buffer_minutes' => 15,
                'is_active' => true,
            ]
        );

        $semeru = Room::firstOrCreate(
            ['name' => 'Ruang Semeru'],
            [
                'capacity' => 25,
                'location' => 'Lantai 3, Sayap Timur',
                'buffer_minutes' => 15,
                'is_active' => true,
            ]
        );

        $bromo = Room::firstOrCreate(
            ['name' => 'Ruang Bromo'],
            [
                'capacity' => 6,
                'location' => 'Lantai 1, Ruang VIP',
                'buffer_minutes' => 0,
                'is_active' => true,
            ]
        );

        // Seed operating hours for Rinjani (Monday to Friday: 08:00 - 18:00)
        for ($day = 1; $day <= 5; $day++) {
            RoomOperatingHour::firstOrCreate(
                ['room_id' => $rinjani->id, 'day_of_week' => $day],
                ['open_time' => '08:00:00', 'close_time' => '18:00:00']
            );
        }
    }
}
