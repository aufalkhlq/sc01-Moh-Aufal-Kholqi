<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        $start = Carbon::now('UTC')->addDays(fake()->numberBetween(1, 10))->setHour(10)->setMinute(0)->setSecond(0);
        $end = $start->copy()->addHour();

        return [
            'room_id' => Room::factory(),
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'start_time' => $start,
            'end_time' => $end,
            'status' => Booking::STATUS_CONFIRMED,
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Booking::STATUS_CANCELLED,
            'cancellation_reason' => 'Meeting no longer needed',
            'cancelled_at' => Carbon::now('UTC'),
        ]);
    }
}
