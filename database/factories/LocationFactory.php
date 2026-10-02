<?php

namespace Database\Factories;

use App\Domain\Locations\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    protected $model = Location::class;

    public function definition(): array
    {
        $name = 'Sede '.fake()->unique()->lastName();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'code' => Str::upper(Str::random(6)),
            'address_line' => fake()->streetAddress(),
            'city' => fake()->city(),
            'department' => fake()->state(),
            'phone' => '607'.fake()->numerify('#######'),
            'email' => fake()->unique()->safeEmail(),
            'timezone' => config('profitlife.display_timezone'),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
