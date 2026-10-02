<?php

namespace Database\Factories;

use App\Domain\Locations\Enums\DayOfWeek;
use App\Domain\Locations\Models\Location;
use App\Domain\Locations\Models\LocationHour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LocationHour>
 */
class LocationHourFactory extends Factory
{
    protected $model = LocationHour::class;

    public function definition(): array
    {
        return [
            'location_id' => Location::factory(),
            'day_of_week' => fake()->randomElement(DayOfWeek::cases()),
            'opens_at' => '06:00:00',
            'closes_at' => '21:00:00',
        ];
    }
}
