<?php

use App\Domain\Appointments\Actions\BookAppointment;
use App\Domain\Appointments\Models\Appointment;
use App\Domain\Appointments\Models\Service;
use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\CheckIns\Actions\RegisterCheckIn;
use App\Domain\CheckIns\DTOs\CheckInOutcome;
use App\Domain\CheckIns\Enums\CheckInMethod;
use App\Domain\Identity\Enums\RoleName;
use App\Domain\Identity\Models\User;
use App\Domain\Locations\Models\Location;
use App\Domain\Members\Models\Member;
use App\Domain\Memberships\Actions\SellMembership;
use App\Domain\Memberships\Models\Membership;
use App\Domain\Memberships\Models\MembershipPlan;
use App\Domain\Staff\Models\Staff;
use App\Domain\Staff\Models\StaffSchedule;
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

/**
 * Vende un plan ya pagado (sin saldo pendiente).
 */
function paidSale(Member $member, Location $location, User $actor, array $planAttributes = [], ?string $startsOn = null): Membership
{
    $plan = planFor($planAttributes);

    return sellTo($member, $plan, $location, $actor, $startsOn, [
        'amount_cents' => $plan->price_cents + (int) $plan->enrollment_fee_cents,
        'method' => PaymentMethod::Cash,
        'reference' => null,
    ]);
}

function registerCheckIn(Member $member, Location $location, ?User $actor = null): CheckInOutcome
{
    return app(RegisterCheckIn::class)
        ->execute($location, CheckInMethod::Manual, $member, null, $actor);
}

/**
 * Servicio ofrecido en las sedes indicadas.
 *
 * @param  list<Location>  $locations
 */
function serviceAt(array $locations, array $attributes = []): Service
{
    $service = Service::factory()->create($attributes);
    $service->locations()->sync(collect($locations)->mapWithKeys(fn (Location $l) => [$l->id => ['is_active' => true]])->all());

    return $service->fresh('locations');
}

/**
 * Profesional agendable que presta los servicios, con franjas diarias en la sede.
 *
 * @param  list<Service>  $services
 */
function professional(Location $location, array $services, string $from = '06:00', string $until = '20:00', RoleName $role = RoleName::Physiotherapist): User
{
    $user = staffUser($role, [$location]);
    $staff = staffOf($user);
    $staff->forceFill(['is_bookable' => true])->save();
    $staff->services()->sync(collect($services)->pluck('id')->all());

    foreach (range(1, 7) as $day) {
        StaffSchedule::query()->create([
            'staff_id' => $staff->id, 'location_id' => $location->id, 'day_of_week' => $day,
            'starts_at' => "{$from}:00", 'ends_at' => "{$until}:00",
        ]);
    }

    return $user;
}

/**
 * Instante UTC a partir de una hora local de Bogotá.
 */
function bogota(string $dateTime): CarbonImmutable
{
    return CarbonImmutable::parse($dateTime, 'America/Bogota')->utc();
}

function book(Member $member, Service $service, Location $location, User $professional, string $localStart, User $actor, ?int $roomId = null): Appointment
{
    return app(BookAppointment::class)->execute(
        $member, $service, $location, staffOf($professional), bogota($localStart), $actor, $roomId,
    );
}
