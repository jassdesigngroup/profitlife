<?php

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Billing\Actions\RecordPayment;
use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Identity\Enums\RoleName;
use App\Domain\Locations\Models\Location;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Settings\Services\Settings;
use App\Livewire\Admin\Members\MemberMemberships;
use App\Support\BusinessDate;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->location = Location::factory()->create(['code' => 'CAB']);
    $this->reception = staffUser(RoleName::Reception, [$this->location]);
    $this->manager = staffUser(RoleName::LocationManager, [$this->location]);
    $this->member = memberAt($this->location);
    $this->plan = planFor(['name' => 'Mensual', 'price_cents' => 15000000, 'enrollment_fee_cents' => 5000000]);
});

it('vende una membresía con matrícula, comprobante numerado y pago', function () {
    Livewire::actingAs($this->reception)->test(MemberMemberships::class, ['memberId' => $this->member->id])
        ->call('openSell')
        ->assertSet('startsOn', BusinessDate::today()->toDateString())
        ->set('planId', (string) $this->plan->id)
        ->assertSet('payAmount', '200000')
        ->set('payMethod', 'bre_b')
        ->set('payReference', 'BRB-123')
        ->call('sell')
        ->assertHasNoErrors();

    $membership = $this->member->memberships()->sole();
    expect($membership->status)->toBe(MembershipStatus::Active)
        ->and($membership->price_cents)->toBe(15000000)
        ->and($membership->ends_on->toDateString())->toBe(BusinessDate::today()->addMonthNoOverflow()->subDay()->toDateString());

    $invoice = Invoice::query()->sole();
    expect($invoice->number)->toBe('CAB-000001')
        ->and($invoice->total_cents)->toBe(20000000)
        ->and($invoice->items)->toHaveCount(2)
        ->and($invoice->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->payments->sole()->method)->toBe(PaymentMethod::BreB)
        ->and($invoice->payments->sole()->reference)->toBe('BRB-123')
        ->and(Activity::query()->where('event', AuditEvent::MembershipSold->value)->exists())->toBeTrue();
});

it('cobra la matrícula solo en la primera membresía y numera de forma consecutiva', function () {
    sellTo($this->member, $this->plan, $this->location, $this->reception);
    $second = sellTo($this->member, $this->plan, $this->location, $this->reception, BusinessDate::today()->addMonthNoOverflow()->toDateString());

    expect($second->status)->toBe(MembershipStatus::Pending);

    $invoices = Invoice::query()->orderBy('id')->get();
    expect($invoices->pluck('number')->all())->toBe(['CAB-000001', 'CAB-000002'])
        ->and($invoices[0]->total_cents)->toBe(20000000)
        ->and($invoices[1]->total_cents)->toBe(15000000);
});

it('no permite membresías solapadas e indica desde cuándo puede empezar', function () {
    $first = sellTo($this->member, $this->plan, $this->location, $this->reception);

    expect(fn () => sellTo($this->member, $this->plan, $this->location, $this->reception))
        ->toThrow(ValidationException::class, 'puede iniciar desde el '.$first->ends_on->addDay()->format('d/m/Y'));
});

it('propone iniciar al día siguiente del fin de la membresía vigente', function () {
    $first = sellTo($this->member, $this->plan, $this->location, $this->reception);

    Livewire::actingAs($this->reception)->test(MemberMemberships::class, ['memberId' => $this->member->id])
        ->call('openSell')
        ->assertSet('startsOn', $first->ends_on->addDay()->toDateString());
});

it('registra abonos y no deja pagar más del saldo', function () {
    sellTo($this->member, $this->plan, $this->location, $this->reception, payment: ['amount_cents' => 5000000, 'method' => PaymentMethod::Cash, 'reference' => null]);

    $invoice = Invoice::query()->sole();
    expect($invoice->status)->toBe(InvoiceStatus::PartiallyPaid)
        ->and($invoice->balanceCents())->toBe(15000000);

    expect(fn () => app(RecordPayment::class)->execute($invoice, 16000000, PaymentMethod::Cash, null, $this->reception))
        ->toThrow(ValidationException::class);
});

it('solo Gerente y Administrador aplican descuentos, con motivo', function () {
    expect(fn () => sellTo($this->member, $this->plan, $this->location, $this->reception, discount: 1000000, reason: 'Amigo'))
        ->toThrow(AuthorizationException::class);

    expect(fn () => sellTo($this->member, $this->plan, $this->location, $this->manager, discount: 1000000))
        ->toThrow(ValidationException::class);

    sellTo($this->member, $this->plan, $this->location, $this->manager, discount: 3000000, reason: 'Convenio empresa');

    $invoice = Invoice::query()->sole();
    expect($invoice->discount_cents)->toBe(3000000)
        ->and($invoice->total_cents)->toBe(17000000)
        ->and(Activity::query()->where('event', AuditEvent::DiscountApplied->value)->sole()->properties['reason'])->toBe('Convenio empresa');

    Livewire::actingAs($this->reception)->test(MemberMemberships::class, ['memberId' => $this->member->id])
        ->call('openSell')
        ->assertDontSee('Descuento (pesos)');
});

it('no vende un plan inactivo o no válido en la sede', function () {
    $other = Location::factory()->create();
    $local = planFor(['access_scope' => 'selected_locations']);
    $local->locations()->sync([$other->id]);

    expect(fn () => sellTo($this->member, $local, $this->location, $this->reception))->toThrow(ValidationException::class);

    $this->plan->update(['is_active' => false]);
    expect(fn () => sellTo($this->member, $this->plan, $this->location, $this->reception))->toThrow(ValidationException::class);
});

it('el comprobante vence según los días de gracia de la sede', function () {
    app(Settings::class)->set('memberships', 'grace_days', 3, $this->location->id);

    sellTo($this->member, $this->plan, $this->location, $this->reception);

    expect(Invoice::query()->sole()->due_on->toDateString())->toBe(BusinessDate::today()->addDays(3)->toDateString());
});

it('usa la fecha local del negocio y no la UTC', function () {
    // 9:30 p. m. en Bogotá = 2:30 a. m. del día siguiente en UTC.
    $this->travelTo(CarbonImmutable::parse('2026-10-02 21:30', 'America/Bogota'));

    expect(BusinessDate::today()->toDateString())->toBe('2026-10-02')
        ->and(now()->utc()->toDateString())->toBe('2026-10-03');

    $membership = sellTo($this->member, $this->plan, $this->location, $this->reception);
    expect($membership->starts_on->toDateString())->toBe('2026-10-02');
});

it('quien no tiene permisos de membresías no ve la pestaña', function () {
    $trainer = staffUser(RoleName::Trainer, [$this->location]);

    Livewire::actingAs($trainer)->test(MemberMemberships::class, ['memberId' => $this->member->id])
        ->assertForbidden();
});
