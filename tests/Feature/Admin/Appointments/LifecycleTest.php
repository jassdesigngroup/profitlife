<?php

use App\Domain\Appointments\Actions\AdjustSessionCredits;
use App\Domain\Appointments\Actions\CancelAppointment;
use App\Domain\Appointments\Actions\MarkAppointment;
use App\Domain\Appointments\Actions\RefundAppointmentCredit;
use App\Domain\Appointments\Actions\RescheduleAppointment;
use App\Domain\Appointments\Enums\AppointmentStatus;
use App\Domain\Appointments\Enums\CreditReason;
use App\Domain\Appointments\Models\SessionCredit;
use App\Domain\Appointments\Notifications\AppointmentNotification;
use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\CheckIns\Enums\RejectionReason;
use App\Domain\Identity\Enums\RoleName;
use App\Domain\Locations\Models\Location;
use App\Domain\Memberships\Actions\CancelMembership;
use App\Domain\Memberships\Enums\SessionPeriod;
use App\Domain\Memberships\Services\ProcessMemberships;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-10-05 07:00', 'America/Bogota'));
    $this->location = Location::factory()->create(['code' => 'CAB']);
    $this->reception = staffUser(RoleName::Reception, [$this->location]);
    $this->manager = staffUser(RoleName::LocationManager, [$this->location]);
    $this->service = serviceAt([$this->location], ['duration_minutes' => 60]);
    $this->physio = professional($this->location, [$this->service], '08:00', '18:00');
    $this->member = memberAt($this->location, ['email' => 'cliente@example.com']);

    $this->plan = planFor();
    $this->plan->planServices()->create(['service_id' => $this->service->id, 'sessions_included' => 4, 'period' => SessionPeriod::PerTerm]);
    $this->membership = sellTo($this->member, $this->plan, $this->location, $this->manager, payment: ['amount_cents' => $this->plan->price_cents, 'method' => PaymentMethod::Cash, 'reference' => null]);
});

function balance($membership): int
{
    return (int) SessionCredit::query()->where('membership_id', $membership->id)->sum('delta');
}

it('reprograma: la original queda reprogramada y la sesión pasa a la nueva', function () {
    $appointment = book($this->member, $this->service, $this->location, $this->physio, '2026-10-06 09:00', $this->reception);

    $new = app(RescheduleAppointment::class)->execute($appointment, staffOf($this->physio), bogota('2026-10-06 15:00'), $this->reception, 'Pidió la tarde');

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Rescheduled)
        ->and($new->rescheduled_from_id)->toBe($appointment->id)
        ->and($new->status)->toBe(AppointmentStatus::Confirmed)
        ->and($new->creditsUsed())->toBe(1)
        ->and($appointment->fresh()->creditsUsed())->toBe(0)
        ->and(balance($this->membership))->toBe(3);

    Notification::assertSentTo($this->member, AppointmentNotification::class, fn ($n) => $n->type === AppointmentNotification::RESCHEDULED);
});

it('reprogramar puede usar el mismo horario de la cita original', function () {
    $appointment = book($this->member, $this->service, $this->location, $this->physio, '2026-10-06 09:00', $this->reception);

    $new = app(RescheduleAppointment::class)->execute($appointment, staffOf($this->physio), bogota('2026-10-06 09:30'), $this->reception);

    expect($new->starts_at->equalTo(bogota('2026-10-06 09:30')))->toBeTrue();
});

it('al reprogramar una sesión suelta el comprobante sigue a la nueva cita', function () {
    $other = memberAt($this->location);
    $appointment = book($other, $this->service, $this->location, $this->physio, '2026-10-06 09:00', $this->reception);
    $invoice = $appointment->invoice();

    $new = app(RescheduleAppointment::class)->execute($appointment, staffOf($this->physio), bogota('2026-10-07 09:00'), $this->reception);

    expect($new->invoice()?->id)->toBe($invoice->id)->and($appointment->fresh()->invoice())->toBeNull();
});

it('cancelar a tiempo devuelve la sesión y anula el comprobante sin pagos', function () {
    $withCredit = book($this->member, $this->service, $this->location, $this->physio, '2026-10-06 09:00', $this->reception);
    app(CancelAppointment::class)->execute($withCredit, 'No puede venir', $this->reception);

    expect($withCredit->fresh()->status)->toBe(AppointmentStatus::Cancelled)
        ->and($withCredit->fresh()->cancelled_by)->toBe($this->reception->id)
        ->and(balance($this->membership))->toBe(4);

    $single = book(memberAt($this->location), $this->service, $this->location, $this->physio, '2026-10-06 11:00', $this->reception);
    app(CancelAppointment::class)->execute($single, 'Cambio de planes', $this->reception);
    expect($single->invoice()->status)->toBe(InvoiceStatus::Void);
});

it('cancelar tarde conserva la sesión descontada y gerencia puede devolverla', function () {
    $appointment = book($this->member, $this->service, $this->location, $this->physio, '2026-10-05 15:00', $this->reception);

    expect(app(CancelAppointment::class)->isLate($appointment))->toBeTrue();
    app(CancelAppointment::class)->execute($appointment, 'Avisó tarde', $this->reception);
    expect(balance($this->membership))->toBe(3);

    expect($this->reception->can('refundCredit', $appointment->fresh()))->toBeFalse()
        ->and($this->manager->can('refundCredit', $appointment->fresh()))->toBeTrue();

    app(RefundAppointmentCredit::class)->execute($appointment->fresh(), 'Calamidad', $this->manager);
    expect(balance($this->membership))->toBe(4)
        ->and($this->manager->can('refundCredit', $appointment->fresh()))->toBeFalse();
});

it('la cancelación exige motivo y solo aplica a citas activas', function () {
    $appointment = book($this->member, $this->service, $this->location, $this->physio, '2026-10-06 09:00', $this->reception);

    expect(fn () => app(CancelAppointment::class)->execute($appointment, ' ', $this->reception))
        ->toThrow(ValidationException::class, 'motivo');

    app(CancelAppointment::class)->execute($appointment, 'Motivo', $this->reception);
    expect(fn () => app(CancelAppointment::class)->execute($appointment->fresh(), 'Otra vez', $this->reception))
        ->toThrow(ValidationException::class, 'ya no está activa');
});

it('marca atendida o inasistencia solo cuando la cita ya empezó', function () {
    $appointment = book($this->member, $this->service, $this->location, $this->physio, '2026-10-05 09:00', $this->reception);

    expect(fn () => app(MarkAppointment::class)->execute($appointment, AppointmentStatus::Completed, $this->physio))
        ->toThrow(ValidationException::class, 'todavía no ha empezado');

    $this->travelTo(CarbonImmutable::parse('2026-10-05 10:30', 'America/Bogota'));
    app(MarkAppointment::class)->execute($appointment, AppointmentStatus::NoShow, $this->reception);

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::NoShow)
        ->and(balance($this->membership))->toBe(3);
});

it('las sesiones vencen con la membresía', function () {
    book($this->member, $this->service, $this->location, $this->physio, '2026-10-06 09:00', $this->reception);
    app(CancelMembership::class)->execute($this->membership, 'Se retira', $this->manager);

    expect(balance($this->membership))->toBe(0)
        ->and(SessionCredit::query()->where('reason', CreditReason::Expire)->sole()->delta)->toBe(-3);
});

it('el proceso diario da de baja el saldo de las membresías vencidas', function () {
    $this->travelTo(CarbonImmutable::parse($this->membership->ends_on->addDay()->toDateString().' 00:05', 'America/Bogota'));
    app(ProcessMemberships::class)->run();

    expect(balance($this->membership))->toBe(0);
});

it('el ajuste manual no deja el saldo negativo', function () {
    app(AdjustSessionCredits::class)->execute($this->membership, $this->service, 2, 'Cortesía', $this->manager);
    expect(balance($this->membership))->toBe(6);

    expect(fn () => app(AdjustSessionCredits::class)->execute($this->membership, $this->service, -7, 'Error', $this->manager))
        ->toThrow(ValidationException::class, 'negativo');
});

describe('check-in con cita', function () {
    it('deja entrar sin membresía desde 60 minutos antes de la cita', function () {
        $patient = memberAt($this->location);
        book($patient, $this->service, $this->location, $this->physio, '2026-10-05 10:00', $this->reception);

        $this->travelTo(CarbonImmutable::parse('2026-10-05 08:30', 'America/Bogota'));
        expect(registerCheckIn($patient, $this->location)->reason())->toBe(RejectionReason::MembershipExpired);

        $this->travelTo(CarbonImmutable::parse('2026-10-05 09:05', 'America/Bogota'));
        $outcome = registerCheckIn($patient, $this->location);

        expect($outcome->accepted())->toBeTrue()
            ->and($outcome->checkIn->membership_id)->toBeNull()
            ->and($outcome->appointment)->not->toBeNull()
            ->and($outcome->warnings[0])->toContain('Ingreso por cita');
    });

    it('un paquete sin acceso al gimnasio no sirve para entrar', function () {
        $package = planFor(['includes_gym_access' => false]);
        $client = memberAt($this->location);
        sellTo($client, $package, $this->location, $this->manager);

        expect(registerCheckIn($client, $this->location)->reason())->toBe(RejectionReason::MembershipExpired);
    });
});
