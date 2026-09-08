<?php

namespace Database\Factories;

use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    protected $model = Room::class;

    public function definition(): array
    {
        return [
            'name' => 'Room '.fake()->words(2, true),
            'capacity' => fake()->numberBetween(4, 30),
            'location' => 'Floor '.fake()->numberBetween(1, 5).', Wing '.fake()->randomElement(['East', 'West', 'North', 'South']),
            'buffer_minutes' => 0,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withBuffer(int $minutes = 15): static
    {
        return $this->state(fn (array $attributes) => [
            'buffer_minutes' => $minutes,
        ]);
    }
}
