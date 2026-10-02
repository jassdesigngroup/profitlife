<?php

namespace Database\Factories;

use App\Domain\Members\Models\EmergencyContact;
use App\Domain\Members\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmergencyContact>
 */
class EmergencyContactFactory extends Factory
{
    protected $model = EmergencyContact::class;

    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'name' => fake()->name(),
            'relationship' => fake()->randomElement(['Madre', 'Padre', 'Pareja', 'Hermano/a', 'Amigo/a']),
            'phone' => '3'.fake()->numerify('#########'),
            'alt_phone' => null,
            'email' => null,
            'is_primary' => false,
        ];
    }
}
