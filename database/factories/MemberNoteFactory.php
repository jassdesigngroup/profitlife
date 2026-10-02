<?php

namespace Database\Factories;

use App\Domain\Identity\Models\User;
use App\Domain\Members\Models\Member;
use App\Domain\Members\Models\MemberNote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemberNote>
 */
class MemberNoteFactory extends Factory
{
    protected $model = MemberNote::class;

    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'author_id' => User::factory(),
            'body' => fake()->sentence(12),
            'is_pinned' => false,
        ];
    }
}
