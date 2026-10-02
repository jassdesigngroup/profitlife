<?php

namespace Database\Factories;

use App\Domain\Consents\Enums\ConsentType;
use App\Domain\Consents\Models\ConsentTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConsentTemplate>
 */
class ConsentTemplateFactory extends Factory
{
    protected $model = ConsentTemplate::class;

    public function definition(): array
    {
        return [
            'type' => ConsentType::DataProcessing,
            'title' => 'Autorización de tratamiento de datos personales',
            'body' => fake()->paragraphs(3, true),
            'version' => 1,
            'is_active' => true,
        ];
    }
}
