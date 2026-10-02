<?php

use App\Domain\Billing\Actions\RecordPayment;
use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Identity\Enums\RoleName;
use App\Domain\Locations\Models\Location;
use App\Domain\Memberships\Actions\ChangeMembershipStatus;
use App\Domain\Memberships\Actions\FreezeMembership;
use App\Domain\Memberships\Actions\UnfreezeMembership;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\Membership;
use App\Domain\Memberships\Services\ProcessMemberships;
use App\Domain\Settings\Services\Settings;
use App\Livewire\Admin\Members\MemberMemberships;
use App\Support\BusinessDate;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-02 10:00', 'America/Bogota'));
    $this->location = Location::factory()->create(['code' => 'CAB']);
    $this->manager = staffUser(RoleName::LocationManager, [$this->location]);
    $this->member = memberAt($this->location);
    app(Settings::class)->set('memberships', 'grace_days', 5);
});

function day(string $date): CarbonImmutable
{
    return CarbonImmutable::createFromFormat('!Y-m-d', $date, 'UTC');
}

function runDaily(string $localDate): array
{
    test()->travelTo(CarbonImmutable::parse("{$localDate} 00:05", 'America/Bogota'));

    return app(ProcessMemberships::class)->run();
}

describe('proceso diario', function () {
    it('activa las membresías que empiezan hoy', function () {
        $m = sellTo($this->member, planFor(), $this->location, $this->manager, '2026-10-10');
        expect($m->status)->toBe(MembershipStatus::Pending);

        runDaily('2026-10-09');
        expect($m->fresh()->status)->toBe(MembershipStatus::Pending);

        runDaily('2026-10-10');
        expect($m->fresh()->status)->toBe(MembershipStatus::Active)
            ->and($m->fresh()->statusHistories()->first()->changed_by)->toBeNull();
    });

    it('vence las membresías sin renovación automática', function () {
        $m = sellTo($this->member, planFor(), $this->location, $this->manager, payment: ['amount_cents' => 15000000, 'method' => PaymentMethod::Cash, 'reference' => null]);

        runDaily('2026-11-01'); // último día de vigencia
        expect($m->fresh()->status)->toBe(MembershipStatus::Active);

        runDaily('2026-11-02');
        expect($m->fresh()->status)->toBe(MembershipStatus::Expired)
            ->and(Membership::count())->toBe(1);
    });

    it('renueva automáticamente con la tarifa vigente y comprobante pendiente con gracia', function () {
        $plan = planFor(['auto_renews' => true]);
        $m = sellTo($this->member, $plan, $this->location, $this->manager, payment: ['amount_cents' => 15000000, 'method' => PaymentMethod::Cash, 'reference' => null]);
        $plan->update(['price_cents' => 16000000]);

        $summary = runDaily('2026-11-02');

        $renewal = Membership::query()->where('renewed_from_id', $m->id)->sole();
        expect($summary['renewed'])->toBe(1)
            ->and($m->fresh()->status)->toBe(MembershipStatus::Expired)
            ->and($renewal->status)->toBe(MembershipStatus::Active)
            ->and($renewal->starts_on->toDateString())->toBe('2026-11-02')
            ->and($renewal->ends_on->toDateString())->toBe('2026-12-01')
            ->and($renewal->price_cents)->toBe(16000000);

        $invoice = $renewal->invoice();
        expect($invoice->status)->toBe(InvoiceStatus::Issued)
            ->and($invoice->total_cents)->toBe(16000000)
            ->and($invoice->due_on->toDateString())->toBe('2026-11-07');
    });

    it('suspende por falta de pago tras la gracia y reactiva al pagar', function () {
        $m = sellTo($this->member, planFor(), $this->location, $this->manager); // vence el 7 de octubre

        runDaily('2026-10-07');
        expect($m->fresh()->status)->toBe(MembershipStatus::Active);

        runDaily('2026-10-08');
        expect($m->fresh()->status)->toBe(MembershipStatus::Suspended);

        $this->travelTo(CarbonImmutable::parse('2026-10-08 15:00', 'America/Bogota'));
        app(RecordPayment::class)->execute($m->invoice(), 15000000, PaymentMethod::Transfer, 'TR-1', $this->manager);

        expect($m->fresh()->status)->toBe(MembershipStatus::Active)
            ->and($m->fresh()->statusHistories()->first()->reason)->toBe('Pago recibido');
    });

    it('un pago no levanta una suspensión manual', function () {
        $m = sellTo($this->member, planFor(), $this->location, $this->manager);
        app(ChangeMembershipStatus::class)->execute($m, MembershipStatus::Suspended, 'Comportamiento', $this->manager);

        app(RecordPayment::class)->execute($m->invoice(), 15000000, PaymentMethod::Cash, null, $this->manager);

        expect($m->fresh()->status)->toBe(MembershipStatus::Suspended);
    });

    it('es idempotente', function () {
        sellTo($this->member, planFor(['auto_renews' => true]), $this->location, $this->manager, payment: ['amount_cents' => 15000000, 'method' => PaymentMethod::Cash, 'reference' => null]);

        runDaily('2026-11-02');
        $second = app(ProcessMemberships::class)->run();

        expect($second)->toBe(['resumed' => 0, 'renewed' => 0, 'expired' => 0, 'activated' => 0, 'suspended' => 0])
            ->and(Membership::count())->toBe(2)
            ->and(Invoice::count())->toBe(2);
    });

    it('se ejecuta con el comando programado', function () {
        $this->artisan('memberships:process')->assertSuccessful()->expectsOutputToContain('Renovadas: 0');
    });
});

describe('congelación', function () {
    it('congela, reanuda y corre el vencimiento por los días congelados', function () {
        $m = sellTo($this->member, planFor(['max_freeze_days' => 15]), $this->location, $this->manager);
        $originalEnd = $m->ends_on;

        app(FreezeMembership::class)->execute($m, null, 'Viaje', $this->manager);
        expect($m->fresh()->status)->toBe(MembershipStatus::Frozen);

        $this->travelTo(CarbonImmutable::parse('2026-10-08 09:00', 'America/Bogota'));
        app(UnfreezeMembership::class)->execute($m->fresh(), $this->manager);

        $m->refresh();
        expect($m->status)->toBe(MembershipStatus::Active)
            ->and($m->ends_on->toDateString())->toBe($originalEnd->addDays(6)->toDateString())
            ->and($m->freezeDaysLeft())->toBe(9);
    });

    it('reanuda sola en la fecha programada', function () {
        $m = sellTo($this->member, planFor(['max_freeze_days' => 15]), $this->location, $this->manager,
            payment: ['amount_cents' => 15000000, 'method' => PaymentMethod::Cash, 'reference' => null]);
        $originalEnd = $m->ends_on;
        app(FreezeMembership::class)->execute($m, day('2026-10-12'), null, $this->manager);

        runDaily('2026-10-11');
        expect($m->fresh()->status)->toBe(MembershipStatus::Frozen);

        runDaily('2026-10-12');
        expect($m->fresh()->status)->toBe(MembershipStatus::Active)
            ->and($m->fresh()->ends_on->toDateString())->toBe($originalEnd->addDays(10)->toDateString());
    });

    it('reanuda sola al agotar los días permitidos', function () {
        $m = sellTo($this->member, planFor(['max_freeze_days' => 5]), $this->location, $this->manager);
        app(FreezeMembership::class)->execute($m, null, null, $this->manager);

        runDaily('2026-10-07');
        expect($m->fresh()->status)->toBe(MembershipStatus::Active)
            ->and($m->fresh()->freezeDaysLeft())->toBe(0);
    });

    it('respeta el máximo de días y los planes sin congelación', function () {
        $m = sellTo($this->member, planFor(['max_freeze_days' => 5]), $this->location, $this->manager);

        expect(fn () => app(FreezeMembership::class)->execute($m, day('2026-10-20'), null, $this->manager))
            ->toThrow(ValidationException::class, 'Solo le quedan 5 días');

        $other = sellTo(memberAt($this->location), planFor(['max_freeze_days' => null]), $this->location, $this->manager);
        expect(fn () => app(FreezeMembership::class)->execute($other, null, null, $this->manager))
            ->toThrow(ValidationException::class, 'no permite congelaciones');
    });

    it('recepción no congela (sin permiso)', function () {
        $m = sellTo($this->member, planFor(), $this->location, $this->manager);
        $reception = staffUser(RoleName::Reception, [$this->location]);

        Livewire::actingAs($reception)->test(MemberMemberships::class, ['memberId' => $this->member->id])
            ->call('openFreeze', $m->id)
            ->assertForbidden();
    });
});

describe('cancelación', function () {
    it('cancela y anula el comprobante sin pagos', function () {
        $m = sellTo($this->member, planFor(['auto_renews' => true]), $this->location, $this->manager);

        Livewire::actingAs($this->manager)->test(MemberMemberships::class, ['memberId' => $this->member->id])
            ->call('openCancel', $m->id)
            ->set('cancelReason', 'Se muda de ciudad')
            ->call('cancel')
            ->assertHasNoErrors();

        $m->refresh();
        expect($m->status)->toBe(MembershipStatus::Cancelled)
            ->and($m->auto_renews)->toBeFalse()
            ->and($m->cancellation_reason)->toBe('Se muda de ciudad')
            ->and($m->invoice()->status)->toBe(InvoiceStatus::Void);
    });

    it('conserva el comprobante si ya tenía pagos', function () {
        $m = sellTo($this->member, planFor(), $this->location, $this->manager, payment: ['amount_cents' => 15000000, 'method' => PaymentMethod::Cash, 'reference' => null]);

        Livewire::actingAs($this->manager)->test(MemberMemberships::class, ['memberId' => $this->member->id])
            ->call('openCancel', $m->id)->set('cancelReason', 'Lesión')->call('cancel');

        expect($m->invoice()->status)->toBe(InvoiceStatus::Paid);
    });
});

it('una membresía vencida deja de ocupar el calendario del cliente', function () {
    $m = sellTo($this->member, planFor(), $this->location, $this->manager, payment: ['amount_cents' => 15000000, 'method' => PaymentMethod::Cash, 'reference' => null]);
    runDaily('2026-11-02');

    $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00', 'America/Bogota'));
    $new = sellTo($this->member, planFor(), $this->location, $this->manager);

    expect($new->starts_on->toDateString())->toBe(BusinessDate::today()->toDateString())
        ->and($m->fresh()->status)->toBe(MembershipStatus::Expired);
});
