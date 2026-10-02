<?php

namespace App\Domain\Appointments\Actions;

use App\Domain\Appointments\DTOs\Coverage;
use App\Domain\Appointments\Enums\AppointmentSource;
use App\Domain\Appointments\Enums\AppointmentStatus;
use App\Domain\Appointments\Models\Appointment;
use App\Domain\Appointments\Models\Service;
use App\Domain\Appointments\Notifications\AppointmentNotification;
use App\Domain\Appointments\Services\AppointmentStatusChanger;
use App\Domain\Appointments\Services\Availability;
use App\Domain\Appointments\Services\SessionLedger;
use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Billing\Actions\IssueInvoice;
use App\Domain\Billing\DTOs\InvoiceLine;
use App\Domain\Identity\Models\User;
use App\Domain\Locations\Models\Location;
use App\Domain\Locations\Models\Room;
use App\Domain\Members\Enums\MemberStatus;
use App\Domain\Members\Models\Member;
use App\Domain\Settings\Services\Settings;
use App\Domain\Staff\Models\Staff;
use App\Support\BusinessDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Agenda una cita confirmada. Para evitar el doble agendamiento bloquea la
 * fila del profesional (y las salas de la sede si el servicio exige sala)
 * con SELECT … FOR UPDATE, revisa cruces y solo entonces inserta.
 *
 * El cobro: sesiones ilimitadas del plan, una sesión del saldo del plan o
 * un comprobante por la sesión suelta.
 */
class BookAppointment
{
    public function __construct(
        private readonly Availability $availability,
        private readonly SessionLedger $ledger,
        private readonly AppointmentStatusChanger $statuses,
        private readonly IssueInvoice $issueInvoice,
        private readonly AuditLogger $audit,
        private readonly Settings $settings,
    ) {}

    public function execute(
        Member $member,
        Service $service,
        Location $location,
        Staff $staff,
        CarbonImmutable $startsAt,
        User $actor,
        ?int $roomId = null,
        ?string $notes = null,
        ?Appointment $rescheduledFrom = null,
        bool $notify = true,
    ): Appointment {
        $service->loadMissing('locations');

        if ($member->status !== MemberStatus::Active) {
            throw ValidationException::withMessages(['member' => 'El cliente no está activo.']);
        }

        if (! $service->isOfferedAt($location->id)) {
            throw ValidationException::withMessages(['serviceId' => 'El servicio no se ofrece en esta sede.']);
        }

        if ($startsAt->lessThan(Date::now()->subMinutes(5))) {
            throw ValidationException::withMessages(['startsAt' => 'No se pueden agendar citas en el pasado.']);
        }

        $appointment = DB::transaction(function () use ($member, $service, $location, $staff, $startsAt, $actor, $roomId, $notes, $rescheduledFrom) {
            // Serializa las reservas del profesional.
            Staff::query()->withoutGlobalScopes()->whereKey($staff->id)->lockForUpdate()->first();

            $ignore = $rescheduledFrom?->id;
            if ($problem = $this->availability->problem($staff, $service, $location, $startsAt, $ignore)) {
                throw ValidationException::withMessages(['startsAt' => $problem]);
            }

            $endsAt = $startsAt->addMinutes($service->duration_minutes);
            $room = null;

            if ($service->requires_room || $roomId !== null) {
                Room::query()->withoutGlobalScopes()->where('location_id', $location->id)->lockForUpdate()->get();
                $room = $this->availability->freeRoom($location, $startsAt, $startsAt->addMinutes($service->blockMinutes()), $roomId, $ignore);

                if ($room === null) {
                    throw ValidationException::withMessages(['roomId' => $roomId ? 'La sala elegida está ocupada en ese horario.' : 'No hay salas libres en ese horario.']);
                }
            }

            $appointment = Appointment::query()->create([
                'member_id' => $member->id,
                'staff_id' => $staff->id,
                'service_id' => $service->id,
                'location_id' => $location->id,
                'room_id' => $room?->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => AppointmentStatus::Confirmed,
                'source' => AppointmentSource::Admin,
                'notes' => $notes,
                'price_cents' => $service->priceCentsAt($location->id),
                'rescheduled_from_id' => $rescheduledFrom?->id,
                'created_by' => $actor->id,
            ]);
            $this->statuses->record($appointment, $rescheduledFrom ? 'Reprogramada' : 'Agendada', $actor);

            $rescheduledFrom !== null
                ? $this->moveCoverage($rescheduledFrom, $appointment, $actor)
                : $this->charge($appointment, $member, $service, $location, $actor);

            if ($rescheduledFrom === null) {
                $this->audit->log('appointments', AuditEvent::AppointmentBooked, $appointment, $actor, [
                    'member_id' => $member->id,
                    'staff_id' => $staff->id,
                    'service_id' => $service->id,
                    'location_id' => $location->id,
                    'starts_at' => $startsAt->toIso8601String(),
                ]);
            }

            return $appointment;
        });

        if ($notify && $rescheduledFrom === null && filled($member->email)) {
            $member->notify(new AppointmentNotification($appointment, AppointmentNotification::BOOKED));
        }

        return $appointment;
    }

    private function charge(Appointment $appointment, Member $member, Service $service, Location $location, User $actor): void
    {
        $localDate = CarbonImmutable::createFromFormat('!Y-m-d', $appointment->starts_at->setTimezone($location->timezone ?: 'UTC')->toDateString(), 'UTC');
        $coverage = $this->ledger->coverage($member, $service, $location->id, $localDate);

        if ($coverage->type === Coverage::CREDIT) {
            $this->ledger->consume($appointment, $coverage->membership, $actor);

            return;
        }

        if ($coverage->type === Coverage::INVOICE && $coverage->priceCents > 0) {
            $when = $appointment->starts_at->setTimezone($location->timezone ?: 'UTC')->format('d/m/Y H:i');

            $this->issueInvoice->execute(
                $member,
                $location,
                [new InvoiceLine(
                    description: "{$service->name} ({$when})",
                    unitPriceCents: $coverage->priceCents,
                    taxRateBps: $service->tax_rate_bps,
                    billableType: 'appointment',
                    billableId: $appointment->id,
                )],
                $service->currency,
                BusinessDate::today()->addDays($this->settings->graceDays($location->id)),
                $actor,
            );
        }
    }

    /**
     * Al reprogramar, la sesión descontada o el comprobante pasan a la nueva cita.
     */
    private function moveCoverage(Appointment $from, Appointment $to, User $actor): void
    {
        $membership = $this->ledger->consumedFrom($from);

        if ($membership !== null && $this->ledger->refund($from, $actor) > 0) {
            $this->ledger->consume($to, $membership, $actor);
        }

        $invoice = $from->invoice();
        if ($invoice !== null) {
            $invoice->items()->where('billable_type', 'appointment')->where('billable_id', $from->id)
                ->update(['billable_id' => $to->id]);
        }
    }
}
