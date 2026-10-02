<?php

namespace Database\Factories;

use App\Domain\Locations\Models\Location;
use App\Domain\Members\Models\Member;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\Membership;
use App\Domain\Memberships\Models\MembershipPlan;
use App\Support\BusinessDate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Membership>
 */
class MembershipFactory extends Factory
{
    protected $model = Membership::class;

    public function definition(): array
    {
        $today = BusinessDate::today();

        return [
            'member_id' => Member::factory(),
            'membership_plan_id' => MembershipPlan::factory(),
            'purchase_location_id' => Location::factory(),
            'status' => MembershipStatus::Active,
            'starts_on' => $today->subDays(10)->toDateString(),
            'ends_on' => $today->addDays(20)->toDateString(),
            'price_cents' => 15000000,
            'currency' => 'COP',
            'auto_renews' => false,
        ];
    }
}
