<?php

namespace Database\Factories;

use App\Domain\Locations\Models\Location;
use App\Domain\Locations\Models\LocationClosure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LocationClosure>
 */
class LocationClosureFactory extends Factory
{
    protected $model = LocationClosure::class;

    public function definition(): array
    {
        return [
            'location_id' => Location::factory(),
            'closed_on' => fake()->dateTimeBetween('+1 week', '+6 months')->format('Y-m-d'),
            'reason' => 'Festivo',
        ];
    }

    public function global(): static
    {
        return $this->state(fn () => ['location_id' => null]);
    }
}
