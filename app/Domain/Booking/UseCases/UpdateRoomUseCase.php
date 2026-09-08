<?php

namespace App\Domain\Booking\UseCases;

use App\Models\Room;
use Illuminate\Support\Facades\DB;

class UpdateRoomUseCase
{
    /**
     * Updates room configuration attributes including active/inactive status.
     *
     * @param  array{name?: string, capacity?: int, location?: string, buffer_minutes?: int, is_active?: bool}  $attributes
     */
    public function execute(Room $room, array $attributes): Room
    {
        return DB::transaction(function () use ($room, $attributes) {
            $room->update($attributes);

            return $room->fresh(['operatingHours']);
        });
    }
}
