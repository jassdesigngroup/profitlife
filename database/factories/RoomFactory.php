<?php

namespace Database\Factories;

use App\Domain\Locations\Enums\RoomType;
use App\Domain\Locations\Models\Location;
use App\Domain\Locations\Models\Room;
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
            'location_id' => Location::factory(),
            'name' => fake()->randomElement(['Consultorio', 'Sala funcional', 'Zona de pesas']).' '.fake()->numberBetween(1, 20),
            'type' => fake()->randomElement(RoomType::cases()),
            'capacity' => fake()->numberBetween(1, 20),
            'is_active' => true,
        ];
    }
}
