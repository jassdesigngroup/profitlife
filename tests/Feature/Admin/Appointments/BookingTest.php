<?php

use App\Domain\Appointments\Actions\BookAppointment;
use App\Domain\Appointments\Enums\AppointmentStatus;
use App\Domain\Appointments\Enums\CreditReason;
use App\Domain\Appointments\Models\Appointment;
use App\Domain\Appointments\Models\SessionCredit;
use App\Domain\Appointments\Notifications\AppointmentNotification;
use App\Domain\Appointments\Services\Availability;
use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Identity\Enums\RoleName;
use App\Domain\Locations\Models\Location;
use App\Domain\Locations\Models\LocationClosure;
use App\Domain\Locations\Models\Room;
use App\Domain\Members\Enums\MemberStatus;
use App\Domain\Memberships\Enums\SessionPeriod;
use App\Domain\Staff\Actions\SaveTimeOff;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-10-05 07:00', 'America/Bogota'));
    $this->location = Location::factory()->create(['code' => 'CAB']);
    $this->other = Location::factory()->create(['code' => 'PRO']);
    $this->reception = staffUser(RoleName::Reception, [$this->location]);
    $this->manager = staffUser(RoleName::LocationManager, [$this->location]);
    $this->service = serviceAt([$this->location], ['duration_minutes' => 60, 'buffer_minutes' => 15, 'price_cents' => 8000000]);
    $this->physio = professional($this->location, [$this->service], '08:00', '12:00');
    $this->member = memberAt($this->location, ['email' => 'cliente@example.com']);
});

it('agenda una sesión suelta: cita confirmada, comprobante y correo', function () {
    $appointment = book($this->member, $this->service, $this->location, $this->physio, '2026-10-05 09:00', $this->reception);

    expect($appointment->status)->toBe(AppointmentStatus::Confirmed)
        ->and($appointment->ends_at->equalTo(bogota('2026-10-05 10:00')))->toBeTrue()
        ->and($appointment->price_cents)->toBe(8000000)
        ->and($appointment->statusHistories()->count())->toBe(1);

    $invoice = $appointment->invoice();
    expect($invoice)->not->toBeNull()
        ->and($invoice->total_cents)->toBe(8000000)
        ->and($invoice->location_id)->toBe($this->location->id);

    Notification::assertSentTo($this->member, AppointmentNotification::class, fn ($n) => $n->type === AppointmentNotification::BOOKED);
});

it('usa el precio propio de la sede', function () {
    $this->service->locations()->updateExistingPivot($this->location->id, ['price_cents' => 6000000]);

    $appointment = book($this->member, $this->service->fresh('locations'), $this->location, $this->physio, '2026-10-05 09:00', $this->reception);

    expect($appointment->price_cents)->toBe(6000000)->and($appointment->invoice()->total_cents)->toBe(6000000);
});

it('descuenta una sesión del plan y no genera comprobante', function () {
    $plan = planFor();
    $plan->planServices()->create(['service_id' => $this->service->id, 'sessions_included' => 4, 'period' => SessionPeriod::PerTerm]);
    $membership = sellTo($this->member, $plan, $this->location, $this->manager, payment: ['amount_cents' => $plan->price_cents, 'method' => PaymentMethod::Cash, 'reference' => null]);

    expect(SessionCredit::query()->where('reason', CreditReason::Grant)->sole()->delta)->toBe(4);

    $appointment = book($this->member, $this->service, $this->location, $this->physio, '2026-10-05 09:00', $this->reception);

    expect($appointment->invoice())->toBeNull()
        ->and($appointment->creditsUsed())->toBe(1)
        ->and((int) SessionCredit::query()->where('membership_id', $membership->id)->sum('delta'))->toBe(3);
});

it('las sesiones ilimitadas no descuentan ni cobran', function () {
    $plan = planFor();
    $plan->planServices()->create(['service_id' => $this->service->id, 'sessions_included' => null, 'period' => SessionPeriod::PerTerm]);
    sellTo($this->member, $plan, $this->location, $this->manager);

    $appointment = book($this->member, $this->service, $this->location, $this->physio, '2026-10-05 09:00', $this->reception);

    expect($appointment->creditsUsed())->toBe(0)->and($appointment->invoice())->toBeNull();
});

it('al agotar las sesiones se cobra como sesión suelta', function () {
    $plan = planFor();
    $plan->planServices()->create(['service_id' => $this->service->id, 'sessions_included' => 1, 'period' => SessionPeriod::PerTerm]);
    sellTo($this->member, $plan, $this->location, $this->manager);

    $first = book($this->member, $this->service, $this->location, $this->physio, '2026-10-05 08:00', $this->reception);
    $second = book($this->member, $this->service, $this->location, $this->physio, '2026-10-05 10:00', $this->reception);

    expect($first->creditsUsed())->toBe(1)->and($second->invoice())->not->toBeNull();
});

it('impide cruces del profesional, contando el margen del servicio', function () {
    book($this->member, $this->service, $this->location, $this->physio, '2026-10-05 09:00', $this->reception);

    // 10:00 cae dentro del margen de 15 minutos de la cita de las 9:00.
    expect(fn () => book(memberAt($this->location), $this->service, $this->location, $this->physio, '2026-10-05 10:00', $this->reception))
        ->toThrow(ValidationException::class, 'ya tiene una cita');

    expect(book(memberAt($this->location), $this->service, $this->location, $this->physio, '2026-10-05 10:15', $this->reception)->exists)->toBeTrue();
});

it('solo agenda dentro de la disponibilidad, sin ausencias ni cierres', function () {
    expect(fn () => book($this->member, $this->service, $this->location, $this->physio, '2026-10-05 11:30', $this->reception))
        ->toThrow(ValidationException::class, 'fuera de la disponibilidad');

    app(SaveTimeOff::class)->execute(staffOf($this->physio), bogota('2026-10-06 00:00'), bogota('2026-10-07 00:00'), 'Vacaciones', $this->manager);
    expect(fn () => book($this->member, $this->service, $this->location, $this->physio, '2026-10-06 09:00', $this->reception))
        ->toThrow(ValidationException::class, 'fuera de la disponibilidad');

    LocationClosure::query()->create(['location_id' => $this->location->id, 'closed_on' => '2026-10-08', 'reason' => 'Festivo']);
    expect(fn () => book($this->member, $this->service, $this->location, $this->physio, '2026-10-08 09:00', $this->reception))
        ->toThrow(ValidationException::class, 'fuera de la disponibilidad');
});

it('rechaza profesionales que no prestan el servicio, sedes sin el servicio, pasado y clientes inactivos', function () {
    $trainer = professional($this->location, [], role: RoleName::Trainer);
    expect(fn () => book($this->member, $this->service, $this->location, $trainer, '2026-10-05 09:00', $this->reception))
        ->toThrow(ValidationException::class, 'no presta este servicio');

    expect(fn () => book($this->member, $this->service, $this->other, $this->physio, '2026-10-05 09:00', $this->reception))
        ->toThrow(ValidationException::class, 'no se ofrece en esta sede');

    expect(fn () => book($this->member, $this->service, $this->location, $this->physio, '2026-10-04 09:00', $this->reception))
        ->toThrow(ValidationException::class, 'en el pasado');

    $this->member->forceFill(['status' => MemberStatus::Inactive])->save();
    expect(fn () => book($this->member, $this->service, $this->location, $this->physio, '2026-10-05 09:00', $this->reception))
        ->toThrow(ValidationException::class, 'no está activo');
});

it('asigna una sala libre cuando el servicio la exige', function () {
    $service = serviceAt([$this->location], ['requires_room' => true, 'duration_minutes' => 60]);
    $other = professional($this->location, [$service], '08:00', '12:00');
    staffOf($this->physio)->services()->attach($service->id);
    $room = Room::factory()->create(['location_id' => $this->location->id, 'name' => 'Consultorio 1']);

    $first = book($this->member, $service, $this->location, $this->physio, '2026-10-05 09:00', $this->reception);
    expect($first->room_id)->toBe($room->id);

    expect(fn () => book(memberAt($this->location), $service, $this->location, $other, '2026-10-05 09:30', $this->reception))
        ->toThrow(ValidationException::class, 'No hay salas libres');
});

it('lista los horarios libres de 15 en 15 minutos', function () {
    book($this->member, $this->service, $this->location, $this->physio, '2026-10-05 09:00', $this->reception);

    $slots = app(Availability::class)->slots($this->service, $this->location, '2026-10-05')
        ->map(fn ($s) => $s['starts_at']->setTimezone('America/Bogota')->format('H:i'))->all();

    // 8:00 no deja margen antes de las 9:00; de 10:15 a 11:00 caben sesiones de 60 minutos.
    expect($slots)->toBe(['10:15', '10:30', '10:45', '11:00']);
});

it('serializa las reservas: la segunda en el mismo horario falla', function () {
    $book = app(BookAppointment::class);
    $staff = staffOf($this->physio);
    $book->execute($this->member, $this->service, $this->location, $staff, bogota('2026-10-05 09:00'), $this->reception);

    expect(fn () => $book->execute(memberAt($this->location), $this->service, $this->location, $staff, bogota('2026-10-05 09:00'), $this->reception))
        ->toThrow(ValidationException::class);

    expect(Appointment::query()->count())->toBe(1);
});
