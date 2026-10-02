<?php

namespace Database\Factories;

use App\Domain\Memberships\Enums\AccessScope;
use App\Domain\Memberships\Enums\DurationUnit;
use App\Domain\Memberships\Models\MembershipPlan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MembershipPlan>
 */
class MembershipPlanFactory extends Factory
{
    protected $model = MembershipPlan::class;

    public function definition(): array
    {
        $name = 'Plan '.fake()->unique()->word();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'description' => null,
            'duration_unit' => DurationUnit::Month,
            'duration_count' => 1,
            'billing_unit' => DurationUnit::Month,
            'billing_count' => 1,
            'price_cents' => 15000000,
            'enrollment_fee_cents' => 0,
            'currency' => 'COP',
            'tax_rate_bps' => 0,
            'access_scope' => AccessScope::AllLocations,
            'visit_limit_count' => null,
            'visit_limit_period' => null,
            'max_freeze_days' => 15,
            'auto_renews' => false,
            'benefits' => [],
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function autoRenewing(): static
    {
        return $this->state(fn () => ['auto_renews' => true]);
    }

    public function selectedLocations(): static
    {
        return $this->state(fn () => ['access_scope' => AccessScope::SelectedLocations]);
    }
}
