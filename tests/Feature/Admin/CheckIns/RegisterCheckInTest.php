<?php

use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\CheckIns\Actions\RegisterCheckIn;
use App\Domain\CheckIns\Enums\CheckInMethod;
use App\Domain\CheckIns\Enums\CheckInResult;
use App\Domain\CheckIns\Enums\RejectionReason;
use App\Domain\CheckIns\Models\CheckIn;
use App\Domain\CheckIns\Services\VisitUsage;
use App\Domain\Identity\Enums\RoleName;
use App\Domain\Locations\Enums\DayOfWeek;
use App\Domain\Locations\Models\Location;
use App\Domain\Locations\Models\LocationClosure;
use App\Domain\Locations\Services\OpeningHours;
use App\Domain\Members\Enums\MemberStatus;
use App\Domain\Memberships\Actions\ChangeMembershipStatus;
use App\Domain\Memberships\Actions\FreezeMembership;
use App\Domain\Memberships\Enums\AccessScope;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Enums\VisitLimitPeriod;
use App\Domain\Memberships\Models\Membership;
use App\Domain\Settings\Services\Settings;
use App\Support\BusinessDate;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-05 08:00', 'America/Bogota'));
    $this->location = Location::factory()->create(['code' => 'CAB']);
    $this->other = Location::factory()->create(['code' => 'PRO']);
    $this->manager = staffUser(RoleName::LocationManager, [$this->location]);
    $this->member = memberAt($this->location);
});

function localAt(string $dateTime): CarbonImmutable
{
    return CarbonImmutable::parse($dateTime, 'America/Bogota');
}

it('acepta con una membresía activa y deja constancia', function () {
    $membership = paidSale($this->member, $this->location, $this->manager);

    $outcome = registerCheckIn($this->member, $this->location, $this->manager);

    expect($outcome->accepted())->toBeTrue()
        ->and($outcome->warnings)->toBe([])
        ->and($outcome->checkIn->membership_id)->toBe($membership->id)
        ->and($outcome->checkIn->method)->toBe(CheckInMethod::Manual)
        ->and($outcome->checkIn->registered_by)->toBe($this->manager->id)
        ->and($outcome->checkIn->location_id)->toBe($this->location->id);
});

it('rechaza sin cliente identificado y guarda el intento', function () {
    $outcome = app(RegisterCheckIn::class)->execute($this->location, CheckInMethod::MemberNumber, null);

    expect($outcome->accepted())->toBeFalse()
        ->and($outcome->reason())->toBe(RejectionReason::NotFound)
        ->and(CheckIn::query()->sole()->member_id)->toBeNull();
});

it('rechaza a un cliente inactivo o bloqueado', function () {
    paidSale($this->member, $this->location, $this->manager);
    $this->member->forceFill(['status' => MemberStatus::Blocked])->save();

    expect(registerCheckIn($this->member, $this->location)->reason())->toBe(RejectionReason::MemberInactive);
});

it('rechaza sin membresía, con una vencida o con una que aún no empieza', function () {
    expect(registerCheckIn($this->member, $this->location)->reason())->toBe(RejectionReason::MembershipExpired);

    $future = memberAt($this->location);
    paidSale($future, $this->location, $this->manager, startsOn: '2026-10-20');
    expect(registerCheckIn($future, $this->location)->reason())->toBe(RejectionReason::MembershipExpired);

    $old = memberAt($this->location);
    Membership::factory()->create([
        'member_id' => $old->id, 'purchase_location_id' => $this->location->id,
        'starts_on' => '2026-08-01', 'ends_on' => '2026-10-04',
    ]);
    expect(registerCheckIn($old, $this->location)->reason())->toBe(RejectionReason::MembershipExpired);
});

it('acepta una membresía por iniciar cuyo día ya llegó', function () {
    $membership = paidSale($this->member, $this->location, $this->manager, startsOn: '2026-10-06');

    $this->travelTo(localAt('2026-10-06 00:02')); // antes del proceso diario de las 00:05

    expect(registerCheckIn($this->member, $this->location)->accepted())->toBeTrue()
        ->and($membership->fresh()->status->value)->toBe('pending');
});

it('rechaza membresías congeladas o suspendidas', function () {
    $frozen = paidSale($this->member, $this->location, $this->manager);
    app(FreezeMembership::class)->execute($frozen, null, null, $this->manager);
    expect(registerCheckIn($this->member, $this->location)->reason())->toBe(RejectionReason::MembershipFrozen);

    $other = memberAt($this->location);
    $suspended = paidSale($other, $this->location, $this->manager);
    app(ChangeMembershipStatus::class)->execute($suspended, MembershipStatus::Suspended, 'Prueba', $this->manager);
    expect(registerCheckIn($other, $this->location)->reason())->toBe(RejectionReason::MembershipSuspended);
});

it('valida las sedes del plan', function () {
    $plan = planFor(['access_scope' => AccessScope::SelectedLocations]);
    $plan->locations()->sync([$this->location->id]);
    sellTo($this->member, $plan, $this->location, $this->manager, payment: ['amount_cents' => $plan->price_cents, 'method' => PaymentMethod::Cash, 'reference' => null]);

    expect(registerCheckIn($this->member, $this->other)->reason())->toBe(RejectionReason::LocationNotAllowed)
        ->and(registerCheckIn($this->member, $this->location)->accepted())->toBeTrue();

    // Un plan de todas las sedes entra en cualquiera.
    $global = memberAt($this->location);
    paidSale($global, $this->location, $this->manager);
    expect(registerCheckIn($global, $this->other)->accepted())->toBeTrue();
});

it('marca como repetido un segundo ingreso dentro de la ventana y lo permite después', function () {
    paidSale($this->member, $this->location, $this->manager);
    app(Settings::class)->set('check_ins', 'duplicate_minutes', 60);

    expect(registerCheckIn($this->member, $this->location)->accepted())->toBeTrue();

    $this->travel(30)->minutes();
    $again = registerCheckIn($this->member, $this->other);
    expect($again->isDuplicate())->toBeTrue()
        ->and($again->checkIn->result)->toBe(CheckInResult::Rejected);

    $this->travel(31)->minutes();
    expect(registerCheckIn($this->member, $this->location)->accepted())->toBeTrue();

    // 0 desactiva la ventana.
    app(Settings::class)->set('check_ins', 'duplicate_minutes', 0);
    expect(registerCheckIn($this->member, $this->location)->accepted())->toBeTrue();
});

it('cuenta un ingreso por día y rechaza al agotar el límite semanal', function () {
    app(Settings::class)->set('check_ins', 'duplicate_minutes', 0);
    paidSale($this->member, $this->location, $this->manager, ['visit_limit_count' => 2, 'visit_limit_period' => VisitLimitPeriod::Week]);

    $first = registerCheckIn($this->member, $this->location);
    expect($first->accepted())->toBeTrue()->and($first->visitsLeft)->toBe(1);

    $this->travelTo(localAt('2026-10-05 18:00'));
    $sameDay = registerCheckIn($this->member, $this->location);
    expect($sameDay->accepted())->toBeTrue()->and($sameDay->visitsLeft)->toBe(1);

    $this->travelTo(localAt('2026-10-07 08:00'));
    $second = registerCheckIn($this->member, $this->location);
    expect($second->accepted())->toBeTrue()->and($second->visitsLeft)->toBe(0)
        ->and($second->warnings)->toContain('Este es su último ingreso disponible del periodo.');

    $this->travelTo(localAt('2026-10-09 08:00'));
    expect(registerCheckIn($this->member, $this->location)->reason())->toBe(RejectionReason::VisitLimitReached);

    // La semana se ancla al inicio de la membresía (lunes 5): el lunes 12 empieza otra.
    $this->travelTo(localAt('2026-10-12 08:00'));
    expect(registerCheckIn($this->member, $this->location)->accepted())->toBeTrue();
});

it('calcula los periodos anclados al inicio de la membresía', function () {
    $membership = Membership::factory()->make(['starts_on' => '2026-01-31', 'ends_on' => '2026-12-30']);
    $usage = app(VisitUsage::class);
    $d = fn (string $date) => CarbonImmutable::createFromFormat('!Y-m-d', $date, 'UTC');

    [$from, $to] = $usage->period($membership, VisitLimitPeriod::Month, $d('2026-02-15'));
    expect([$from->toDateString(), $to->toDateString()])->toBe(['2026-01-31', '2026-02-27']);

    [$from, $to] = $usage->period($membership, VisitLimitPeriod::Month, $d('2026-02-28'));
    expect([$from->toDateString(), $to->toDateString()])->toBe(['2026-02-28', '2026-03-30']);

    [$from, $to] = $usage->period($membership, VisitLimitPeriod::Week, $d('2026-02-10'));
    expect([$from->toDateString(), $to->toDateString()])->toBe(['2026-02-07', '2026-02-13']);

    [$from, $to] = $usage->period($membership, VisitLimitPeriod::Term, $d('2026-05-01'));
    expect([$from->toDateString(), $to->toDateString()])->toBe(['2026-01-31', '2026-12-30']);
});

it('acepta con avisos: saldo pendiente en gracia, fuera de horario y vencimiento cercano', function () {
    app(Settings::class)->set('memberships', 'grace_days', 5);
    sellTo($this->member, planFor(['price_cents' => 15000000]), $this->location, $this->manager, startsOn: '2026-09-07');

    $this->location->hours()->create(['day_of_week' => DayOfWeek::Monday, 'opens_at' => '09:00:00', 'closes_at' => '20:00:00']);

    $outcome = registerCheckIn($this->member, $this->location);

    expect($outcome->accepted())->toBeTrue()
        ->and(implode(' ', $outcome->warnings))->toContain('saldo pendiente')
        ->and($outcome->warnings)->toContain('Ingreso fuera del horario de atención de la sede.')
        ->and($outcome->warnings)->toContain('La membresía vence el 06/10/2026.');
});

it('respeta los cierres y las franjas de la sede', function () {
    $hours = app(OpeningHours::class);
    $this->location->hours()->create(['day_of_week' => DayOfWeek::Monday, 'opens_at' => '06:00:00', 'closes_at' => '21:00:00']);

    expect($hours->isOpenAt($this->location, localAt('2026-10-05 08:00')))->toBeTrue()
        ->and($hours->isOpenAt($this->location, localAt('2026-10-05 21:30')))->toBeFalse()
        ->and($hours->isOpenAt($this->location, localAt('2026-10-06 08:00')))->toBeFalse();

    LocationClosure::query()->create(['location_id' => null, 'closed_on' => '2026-10-05', 'reason' => 'Festivo']);
    expect($hours->isOpenAt($this->location->fresh(), localAt('2026-10-05 08:00')))->toBeFalse();

    // Sin franjas configuradas no se avisa.
    expect($hours->isOpenAt($this->other, localAt('2026-10-06 03:00')))->toBeTrue();
});

it('la fecha del ingreso usa el día local', function () {
    expect(BusinessDate::today()->toDateString())->toBe('2026-10-05');
});
