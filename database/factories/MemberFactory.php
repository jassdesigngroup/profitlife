<?php

namespace Database\Factories;

use App\Domain\Locations\Models\Location;
use App\Domain\Members\Enums\Gender;
use App\Domain\Members\Enums\MemberStatus;
use App\Domain\Members\Models\Member;
use App\Domain\Shared\Enums\DocumentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
{
    protected $model = Member::class;

    public function definition(): array
    {
        return [
            'member_number' => 'TST-'.fake()->unique()->numerify('#######'),
            'home_location_id' => Location::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'document_type' => DocumentType::CitizenshipCard,
            'document_number' => fake()->unique()->numerify('1#########'),
            'birth_date' => fake()->dateTimeBetween('-60 years', '-19 years')->format('Y-m-d'),
            'gender' => fake()->randomElement(Gender::cases()),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '3'.fake()->unique()->numerify('#########'),
            'address_line' => fake()->streetAddress(),
            'city' => 'Bucaramanga',
            'department' => 'Santander',
            'status' => MemberStatus::Active,
            'joined_on' => fake()->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
        ];
    }

    public function minor(): static
    {
        return $this->state(fn () => [
            'birth_date' => now()->subYears(15)->toDateString(),
            'document_type' => DocumentType::IdentityCard,
        ]);
    }
}
