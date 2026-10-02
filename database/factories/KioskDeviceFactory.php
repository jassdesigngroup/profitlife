<?php

namespace Database\Factories;

use App\Domain\CheckIns\Models\KioskDevice;
use App\Domain\Locations\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KioskDevice>
 */
class KioskDeviceFactory extends Factory
{
    protected $model = KioskDevice::class;

    public function definition(): array
    {
        return [
            'location_id' => Location::factory(),
            'name' => 'Tablet '.fake()->randomElement(['entrada', 'recepción', 'piso 2']),
            'is_active' => true,
        ];
    }
}
