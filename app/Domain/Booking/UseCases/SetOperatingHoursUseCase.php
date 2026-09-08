<?php

namespace App\Domain\Booking\UseCases;

use App\Models\Room;
use App\Models\RoomOperatingHour;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class SetOperatingHoursUseCase
{
    /**
     * Replaces or clears operating hours for a room dynamically.
     *
     * @param  array<int, array{day_of_week: int, open_time: string, close_time: string}>  $hours
     * @return Collection<int, RoomOperatingHour>
     */
    public function execute(Room $room, array $hours = []): Collection
    {
        return DB::transaction(function () use ($room, $hours) {
            $room->operatingHours()->delete();

            foreach ($hours as $item) {
                $room->operatingHours()->create([
                    'day_of_week' => (int) $item['day_of_week'],
                    'open_time' => $item['open_time'],
                    'close_time' => $item['close_time'],
                ]);
            }

            return $room->operatingHours()->orderBy('day_of_week')->get();
        });
    }
}
