<?php

use App\Domain\Identity\Enums\RoleName;
use App\Domain\Identity\Models\User;
use App\Domain\Locations\Models\Location;
use App\Domain\Members\Models\Member;
use App\Domain\Memberships\Actions\SellMembership;
use App\Domain\Memberships\Models\Membership;
use App\Domain\Memberships\Models\MembershipPlan;
use App\Domain\Staff\Models\Staff;
use App\Support\BusinessDate;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/**
 * Crea un usuario de staff con un rol, sus sedes y, por defecto, 2FA confirmado
 * (requisito de acceso para algunos roles).
 *
 * @param  list<Location>  $locations
 */
function staffUser(RoleName $role, array $locations = [], bool $twoFactor = true, array $userAttributes = []): User
{
    $factory = User::factory()->role($role);

    if ($twoFactor) {
        $factory = $factory->withTwoFactor();
    }

    $user = $factory->create($userAttributes);

    Staff::factory()->for($user)->atLocations(...$locations)->create();

    return $user->fresh();
}

function staffOf(User $user): Staff
{
    return Staff::query()->withoutGlobalScopes()->where('user_id', $user->id)->firstOrFail();
}

function memberAt(Location $location, array $attributes = []): Member
{
    return Member::factory()->create(['home_location_id' => $location->id] + $attributes);
}

function planFor(array $attributes = []): MembershipPlan
{
    return MembershipPlan::factory()->create($attributes);
}

function sellTo(Member $member, MembershipPlan $plan, Location $location, User $actor, ?string $startsOn = null, ?array $payment = null, int $discount = 0, ?string $reason = null): Membership
{
    return app(SellMembership::class)->execute(
        $member, $plan, $location,
        $startsOn ? CarbonImmutable::createFromFormat('!Y-m-d', $startsOn, 'UTC') : BusinessDate::today(),
        $actor, $discount, $reason, $payment,
    );
}
