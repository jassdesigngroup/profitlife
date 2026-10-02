<?php

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Billing\Actions\RecordPayment;
use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Payment;
use App\Domain\Identity\Enums\RoleName;
use App\Domain\Locations\Models\Location;
use App\Domain\Settings\Services\Settings;
use App\Livewire\Admin\Billing\PaymentIndex;
use App\Livewire\Admin\Members\MemberBilling;
use App\Livewire\Admin\Settings\SettingsPage;
use App\Support\Money;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->location = Location::factory()->create(['code' => 'CAB']);
    $this->reception = staffUser(RoleName::Reception, [$this->location]);
    $this->manager = staffUser(RoleName::LocationManager, [$this->location]);
    $this->member = memberAt($this->location);
    sellTo($this->member, planFor(['price_cents' => 15000000]), $this->location, $this->manager);
    $this->invoice = Invoice::query()->sole();
});

it('registra pagos desde la ficha y audita', function () {
    Livewire::actingAs($this->reception)->test(MemberBilling::class, ['memberId' => $this->member->id])
        ->assertSee('CAB-000001')
        ->call('openPay', $this->invoice->id)
        ->assertSet('amount', '150000')
        ->set('amount', '100.000')
        ->set('method', 'nequi')
        ->set('reference', 'NQ-55')
        ->call('pay')
        ->assertHasNoErrors();

    expect($this->invoice->fresh()->status)->toBe(InvoiceStatus::PartiallyPaid)
        ->and($this->invoice->fresh()->paid_cents)->toBe(10000000)
        ->and(Activity::query()->where('event', AuditEvent::PaymentRecorded->value)->exists())->toBeTrue();
});

it('no acepta pagos por pasarela registrados a mano', function () {
    Livewire::actingAs($this->reception)->test(MemberBilling::class, ['memberId' => $this->member->id])
        ->call('openPay', $this->invoice->id)
        ->set('method', 'gateway')
        ->call('pay')
        ->assertHasErrors('method');
});

it('anula un pago (con permiso) y el comprobante recupera el saldo', function () {
    app(RecordPayment::class)->execute($this->invoice, 15000000, PaymentMethod::Cash, null, $this->reception);
    $payment = Payment::query()->sole();

    Livewire::actingAs($this->reception)->test(MemberBilling::class, ['memberId' => $this->member->id])
        ->call('openVoidPayment', $payment->id)->assertForbidden();

    Livewire::actingAs($this->manager)->test(MemberBilling::class, ['memberId' => $this->member->id])
        ->call('openVoidPayment', $payment->id)
        ->set('voidReason', 'Se registró dos veces')
        ->call('void')
        ->assertHasNoErrors();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Cancelled)
        ->and($this->invoice->fresh()->status)->toBe(InvoiceStatus::Issued)
        ->and($this->invoice->fresh()->paid_cents)->toBe(0);
});

it('solo anula comprobantes sin pagos', function () {
    app(RecordPayment::class)->execute($this->invoice, 1000000, PaymentMethod::Cash, null, $this->reception);

    Livewire::actingAs($this->manager)->test(MemberBilling::class, ['memberId' => $this->member->id])
        ->call('openVoidInvoice', $this->invoice->id)->assertForbidden();
});

it('muestra el comprobante imprimible con la aclaración legal', function () {
    $this->actingAs($this->reception)->get(route('admin.invoices.show', $this->invoice))
        ->assertOk()
        ->assertSee('CAB-000001')
        ->assertSee('No es una factura electrónica de venta.');
});

it('la caja suma por medio de pago en el rango local', function () {
    $record = app(RecordPayment::class);
    $record->execute($this->invoice, 5000000, PaymentMethod::Cash, null, $this->reception);
    $record->execute($this->invoice, 4000000, PaymentMethod::BreB, 'B1', $this->reception);

    Livewire::actingAs($this->reception)->test(PaymentIndex::class)
        ->assertSee(Money::ofCents(9000000)->format())
        ->assertSee('Bre-B')
        ->assertSee(Money::ofCents(4000000)->format());
});

it('los ajustes de gracia los cambia solo quien tiene settings.update', function () {
    $admin = staffUser(RoleName::Admin, [$this->location]);

    Livewire::actingAs($this->manager)->test(SettingsPage::class)->assertForbidden();

    Livewire::actingAs($admin)->test(SettingsPage::class)
        ->set('graceDays', 8)
        ->set("locationGrace.{$this->location->id}", '2')
        ->call('save')
        ->assertHasNoErrors();

    $settings = app(Settings::class);
    expect($settings->graceDays())->toBe(8)
        ->and($settings->graceDays($this->location->id))->toBe(2)
        ->and($settings->graceDays(Location::factory()->create()->id))->toBe(8)
        ->and(Activity::query()->where('event', AuditEvent::SettingsUpdated->value)->exists())->toBeTrue();

    Livewire::actingAs($admin)->test(SettingsPage::class)
        ->set("locationGrace.{$this->location->id}", '')
        ->call('save');
    expect(app(Settings::class)->graceDays($this->location->id))->toBe(8);
});
