<?php

namespace Database\Factories;

use App\Domain\Identity\Models\User;
use App\Domain\Locations\Models\Location;
use App\Domain\Shared\Enums\DocumentType;
use App\Domain\Staff\Enums\StaffStatus;
use App\Domain\Staff\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Staff>
 */
class StaffFactory extends Factory
{
    protected $model = Staff::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'document_type' => DocumentType::CitizenshipCard,
            'document_number' => fake()->unique()->numerify('10########'),
            'phone' => '3'.fake()->numerify('#########'),
            'job_title' => fake()->randomElement(['Entrenador', 'Fisioterapeuta', 'Recepcionista', 'Coordinador']),
            'professional_license' => null,
            'calendar_color' => fake()->hexColor(),
            'is_bookable' => false,
            'status' => StaffStatus::Active,
            'hired_on' => fake()->dateTimeBetween('-3 years', 'now')->format('Y-m-d'),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => StaffStatus::Inactive]);
    }

    /**
     * Asigna el empleado a las sedes indicadas; la primera queda como principal.
     */
    public function atLocations(Location ...$locations): static
    {
        return $this->afterCreating(function (Staff $staff) use ($locations) {
            foreach (array_values($locations) as $i => $location) {
                $staff->locations()->attach($location->getKey(), ['is_primary' => $i === 0]);
            }
        });
    }
}
