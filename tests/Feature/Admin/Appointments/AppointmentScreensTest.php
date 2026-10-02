<?php

use App\Domain\Appointments\Enums\AppointmentStatus;
use App\Domain\Appointments\Models\Appointment;
use App\Domain\Appointments\Models\Service;
use App\Domain\Appointments\Models\SessionCredit;
use App\Domain\Identity\Enums\RoleName;
use App\Domain\Identity\Models\User;
use App\Domain\Locations\Models\Location;
use App\Domain\Memberships\Enums\SessionPeriod;
use App\Domain\Memberships\Models\MembershipPlan;
use App\Domain\Settings\Services\Settings;
use App\Domain\Staff\Models\StaffSchedule;
use App\Livewire\Admin\Appointments\Agenda;
use App\Livewire\Admin\Appointments\AppointmentPanel;
use App\Livewire\Admin\Appointments\BookingModal;
use App\Livewire\Admin\Members\MemberAppointments;
use App\Livewire\Admin\Plans\PlanForm;
use App\Livewire\Admin\Services\ServiceForm;
use App\Livewire\Admin\Settings\SettingsPage;
use App\Livewire\Admin\Staff\StaffAvailability;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-10-05 07:00', 'America/Bogota'));
    $this->location = Location::factory()->create(['name' => 'Cabecera']);
    $this->other = Location::factory()->create(['name' => 'Provenza']);
    $this->reception = staffUser(RoleName::Reception, [$this->location]);
    $this->manager = staffUser(RoleName::LocationManager, [$this->location]);
    $this->service = serviceAt([$this->location], ['name' => 'Fisioterapia', 'duration_minutes' => 60]);
    $this->physio = professional($this->location, [$this->service], '08:00', '12:00');
    $this->trainer = professional($this->location, [$this->service], '08:00', '12:00', RoleName::Trainer);
    $this->member = memberAt($this->location, ['first_name' => 'Laura', 'last_name' => 'Quintero']);
});

function slotFor(User $professional, string $local): string
{
    return staffOf($professional)->id.'|'.bogota($local)->toIso8601ZuluString();
}

describe('agendar desde el modal', function () {
    it('recepción agenda con cualquier profesional', function () {
        Livewire::actingAs($this->reception)->test(BookingModal::class)
            ->call('open', memberId: $this->member->id)
            ->assertSet('show', true)
            ->set('serviceId', (string) $this->service->id)
            ->set('date', '2026-10-05')
            ->assertSee('8:00 am')
            ->set('pickedSlot', slotFor($this->physio, '2026-10-05 09:00'))
            ->call('book')
            ->assertHasNoErrors()
            ->assertSet('show', false)
            ->assertDispatched('appointments-changed');

        expect(Appointment::query()->sole()->staff_id)->toBe(staffOf($this->physio)->id);
    });

    it('al abrir desde un hueco preselecciona servicio y horario', function () {
        Livewire::actingAs($this->reception)->test(BookingModal::class)
            ->call('open', staffId: staffOf($this->physio)->id, date: '2026-10-05', time: '09:00')
            ->assertSet('serviceId', (string) $this->service->id)
            ->assertSet('pickedSlot', slotFor($this->physio, '2026-10-05 09:00'));
    });

    it('el profesional solo agenda en su propia agenda', function () {
        Livewire::actingAs($this->trainer)->test(BookingModal::class)
            ->call('open', memberId: $this->member->id)
            ->assertSet('staffId', (string) staffOf($this->trainer)->id)
            ->set('serviceId', (string) $this->service->id)
            ->set('date', '2026-10-05')
            ->set('pickedSlot', slotFor($this->physio, '2026-10-05 09:00'))
            ->call('book')
            ->assertForbidden();

        expect(Appointment::query()->count())->toBe(0);
    });

    it('no agenda en sedes ajenas ni a clientes que no ve', function () {
        $foreign = memberAt($this->other);

        Livewire::actingAs($this->reception)->test(BookingModal::class)
            ->call('open', memberId: $foreign->id)
            ->assertNotFound();

        Livewire::actingAs($this->reception)->test(BookingModal::class)
            ->call('open', memberId: $this->member->id)
            ->set('locationId', (string) $this->other->id)
            ->set('serviceId', (string) $this->service->id)
            ->set('pickedSlot', slotFor($this->physio, '2026-10-05 09:00'))
            ->call('book')
            ->assertHasErrors('locationId');
    });

    it('muestra cómo se cobrará la cita', function () {
        $plan = planFor();
        $plan->planServices()->create(['service_id' => $this->service->id, 'sessions_included' => 3, 'period' => SessionPeriod::PerTerm]);
        sellTo($this->member, $plan, $this->location, $this->manager);

        Livewire::actingAs($this->reception)->test(BookingModal::class)
            ->call('open', memberId: $this->member->id)
            ->set('serviceId', (string) $this->service->id)
            ->assertSee('quedan 2');
    });
});

describe('detalle de la cita', function () {
    beforeEach(function () {
        $this->appointment = book($this->member, $this->service, $this->location, $this->physio, '2026-10-06 09:00', $this->reception);
    });

    it('el profesional ve sus citas pero no las de otros', function () {
        Livewire::actingAs($this->physio)->test(AppointmentPanel::class)
            ->call('open', $this->appointment->id)->assertSet('show', true)->assertSee('Laura Quintero');

        Livewire::actingAs($this->trainer)->test(AppointmentPanel::class)
            ->call('open', $this->appointment->id)->assertForbidden();
    });

    it('recepción cancela con motivo', function () {
        Livewire::actingAs($this->reception)->test(AppointmentPanel::class)
            ->call('open', $this->appointment->id)
            ->call('setMode', 'cancel')
            ->call('cancel')->assertHasErrors('cancelReason')
            ->set('cancelReason', 'No puede venir')
            ->call('cancel')
            ->assertHasNoErrors()
            ->assertDispatched('appointments-changed');

        expect($this->appointment->fresh()->status)->toBe(AppointmentStatus::Cancelled);
    });

    it('reprograma eligiendo un horario libre', function () {
        Livewire::actingAs($this->reception)->test(AppointmentPanel::class)
            ->call('open', $this->appointment->id)
            ->call('setMode', 'reschedule')
            ->set('newDate', '2026-10-07')
            ->set('newSlot', slotFor($this->physio, '2026-10-07 10:00'))
            ->call('reschedule')
            ->assertHasNoErrors();

        expect($this->appointment->fresh()->status)->toBe(AppointmentStatus::Rescheduled)
            ->and(Appointment::query()->where('rescheduled_from_id', $this->appointment->id)->exists())->toBeTrue();
    });

    it('recepción no puede devolver sesiones', function () {
        Livewire::actingAs($this->reception)->test(AppointmentPanel::class)
            ->call('open', $this->appointment->id)
            ->call('setMode', 'refund')
            ->assertForbidden();
    });
});

describe('agenda', function () {
    it('recepción ve a todos los profesionales y el profesional solo su columna', function () {
        book($this->member, $this->service, $this->location, $this->physio, '2026-10-05 09:00', $this->reception);

        Livewire::actingAs($this->reception)->test(Agenda::class, ['locationId' => (string) $this->location->id])
            ->set('date', '2026-10-05')
            ->assertSee(staffOf($this->physio)->full_name)
            ->assertSee(staffOf($this->trainer)->full_name)
            ->assertSee('Laura Quintero');

        Livewire::actingAs($this->trainer)->test(Agenda::class)
            ->set('date', '2026-10-05')
            ->assertSee(staffOf($this->trainer)->full_name)
            ->assertDontSee('Laura Quintero');
    });
});

describe('administración', function () {
    it('el administrador crea un servicio con profesionales y precio por sede', function () {
        $admin = staffUser(RoleName::Admin, [$this->location]);

        Livewire::actingAs($admin)->test(ServiceForm::class)
            ->set('name', 'Masaje deportivo')
            ->set('category', 'physiotherapy')
            ->set('duration_minutes', 45)
            ->set('price', '70.000')
            ->set('staffIds', [(string) staffOf($this->physio)->id])
            ->set("locationPrices.{$this->other->id}.enabled", false)
            ->set("locationPrices.{$this->location->id}.price", '65.000')
            ->call('save')
            ->assertHasNoErrors();

        $service = Service::query()->where('name', 'Masaje deportivo')->sole()->load('locations');
        expect($service->price_cents)->toBe(7000000)
            ->and($service->priceCentsAt($this->location->id))->toBe(6500000)
            ->and($service->isOfferedAt($this->other->id))->toBeFalse()
            ->and($service->staff()->count())->toBe(1);

        Livewire::actingAs($this->manager)->test(ServiceForm::class)->assertForbidden();
    });

    it('el gerente define la disponibilidad y no acepta franjas cruzadas', function () {
        $staff = staffOf($this->physio);

        Livewire::actingAs($this->manager)->test(StaffAvailability::class, ['staff' => $staff])
            ->set('schedules', [
                ['location_id' => (string) $this->location->id, 'day_of_week' => '1', 'starts_at' => '06:00', 'ends_at' => '10:00'],
                ['location_id' => (string) $this->location->id, 'day_of_week' => '1', 'starts_at' => '09:00', 'ends_at' => '12:00'],
            ])
            ->call('saveSchedules')
            ->assertHasErrors('schedules');

        Livewire::actingAs($this->manager)->test(StaffAvailability::class, ['staff' => $staff])
            ->set('schedules', [['location_id' => (string) $this->location->id, 'day_of_week' => '2', 'starts_at' => '14:00', 'ends_at' => '18:00']])
            ->call('saveSchedules')
            ->assertHasNoErrors();

        expect(StaffSchedule::query()->where('staff_id', $staff->id)->count())->toBe(1);

        Livewire::actingAs($this->reception)->test(StaffAvailability::class, ['staff' => $staff])->assertForbidden();
    });

    it('registra ausencias y avisa de las citas afectadas', function () {
        book($this->member, $this->service, $this->location, $this->physio, '2026-10-06 09:00', $this->reception);

        Livewire::actingAs($this->manager)->test(StaffAvailability::class, ['staff' => staffOf($this->physio)])
            ->set('offFrom', '2026-10-06T00:00')
            ->set('offUntil', '2026-10-07T00:00')
            ->set('offReason', 'Vacaciones')
            ->call('addTimeOff')
            ->assertHasNoErrors()
            ->assertDispatched('toast', message: 'Ausencia registrada. Hay 1 citas en ese periodo: reprográmelas desde la agenda.', type: 'warning');
    });

    it('el plan guarda sus sesiones incluidas y el acceso al gimnasio', function () {
        $admin = staffUser(RoleName::Admin, [$this->location]);

        Livewire::actingAs($admin)->test(PlanForm::class)
            ->set('name', 'Paquete 10 fisioterapias')
            ->set('price', '750.000')
            ->set('duration_count', 3)
            ->set('includes_gym_access', false)
            ->call('addSession')
            ->set('sessions.0.service_id', (string) $this->service->id)
            ->set('sessions.0.sessions', '10')
            ->call('save')
            ->assertHasNoErrors();

        $plan = MembershipPlan::query()->where('name', 'Paquete 10 fisioterapias')->sole();
        expect($plan->includes_gym_access)->toBeFalse()
            ->and($plan->planServices()->sole()->sessions_included)->toBe(10);
    });

    it('solo gerencia ajusta el saldo de sesiones desde la ficha', function () {
        $plan = planFor();
        $plan->planServices()->create(['service_id' => $this->service->id, 'sessions_included' => 2, 'period' => SessionPeriod::PerTerm]);
        $membership = sellTo($this->member, $plan, $this->location, $this->manager);

        Livewire::actingAs($this->reception)->test(MemberAppointments::class, ['memberId' => $this->member->id])
            ->call('openAdjust')->assertForbidden();

        Livewire::actingAs($this->manager)->test(MemberAppointments::class, ['memberId' => $this->member->id])
            ->assertSee('Fisioterapia')
            ->call('openAdjust')
            ->set('adjustMembershipId', (string) $membership->id)
            ->set('adjustServiceId', (string) $this->service->id)
            ->set('adjustDelta', 3)
            ->set('adjustReason', 'Cortesía')
            ->call('adjust')
            ->assertHasNoErrors();

        expect((int) SessionCredit::query()->where('membership_id', $membership->id)->sum('delta'))->toBe(5);
    });

    it('los ajustes guardan las horas de cancelación', function () {
        $admin = staffUser(RoleName::Admin, [$this->location]);

        Livewire::actingAs($admin)->test(SettingsPage::class)->set('cancellationHours', 24)->call('save')->assertHasNoErrors();

        expect(app(Settings::class)->cancellationHours())->toBe(24);
    });
});
