<?php

namespace App\Http\Resources;

use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Booking
 */
class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $timezone = $request->query('timezone') ?? $request->header('X-Timezone') ?? 'UTC';

        $startTimeUtc = Carbon::parse($this->start_time)->utc();
        $endTimeUtc = Carbon::parse($this->end_time)->utc();

        $data = [
            'id' => $this->id,
            'room_id' => $this->room_id,
            'user_id' => $this->user_id,
            'recurrence_rule_id' => $this->recurrence_rule_id,
            'title' => $this->title,
            'status' => $this->status,
            'start_time_utc' => $startTimeUtc->toIso8601String(),
            'end_time_utc' => $endTimeUtc->toIso8601String(),
            'duration_minutes' => (int) $startTimeUtc->diffInMinutes($endTimeUtc),
            'cancellation_reason' => $this->cancellation_reason,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'room' => $this->whenLoaded('room', fn () => [
                'id' => $this->room->id,
                'name' => $this->room->name,
                'capacity' => $this->room->capacity,
                'location' => $this->room->location,
                'buffer_minutes' => $this->room->buffer_minutes,
            ]),
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        // Explicit timezone edge conversion
        if ($timezone !== 'UTC') {
            try {
                $data['timezone'] = $timezone;
                $data['start_time_local'] = $startTimeUtc->copy()->setTimezone($timezone)->toIso8601String();
                $data['end_time_local'] = $endTimeUtc->copy()->setTimezone($timezone)->toIso8601String();
            } catch (\Throwable) {
                $data['timezone'] = 'UTC';
            }
        }

        return $data;
    }
}
