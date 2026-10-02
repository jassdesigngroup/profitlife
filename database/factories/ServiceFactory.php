<?php

namespace Database\Factories;

use App\Domain\Appointments\Enums\ServiceCategory;
use App\Domain\Appointments\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        $name = 'Fisioterapia '.fake()->unique()->numerify('###');

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'category' => ServiceCategory::Physiotherapy,
            'duration_minutes' => 60,
            'buffer_minutes' => 0,
            'price_cents' => 8000000,
            'currency' => 'COP',
            'tax_rate_bps' => 0,
            'requires_room' => false,
            'is_clinical' => true,
            'is_bookable_online' => false,
            'color' => '#FD540D',
            'is_active' => true,
        ];
    }
}
