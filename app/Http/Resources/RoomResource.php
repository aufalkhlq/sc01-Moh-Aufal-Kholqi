<?php

namespace App\Http\Resources;

use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Room
 */
class RoomResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'capacity' => $this->capacity,
            'location' => $this->location,
            'buffer_minutes' => $this->buffer_minutes,
            'is_active' => $this->is_active,
            'operating_hours' => $this->whenLoaded('operatingHours', fn () => $this->operatingHours->map(fn ($h) => [
                'day_of_week' => $h->day_of_week,
                'open_time' => $h->open_time,
                'close_time' => $h->close_time,
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
