<?php

namespace App\Livewire\Admin\Appointments;

use App\Domain\Appointments\Actions\CancelAppointment;
use App\Domain\Appointments\Actions\MarkAppointment;
use App\Domain\Appointments\Actions\RefundAppointmentCredit;
use App\Domain\Appointments\Actions\RescheduleAppointment;
use App\Domain\Appointments\Enums\AppointmentStatus;
use App\Domain\Appointments\Models\Appointment;
use App\Domain\Appointments\Services\Availability;
use App\Domain\Physiotherapy\Models\PhysiotherapyRecord;
use App\Domain\Staff\Models\Staff;
use App\Livewire\Admin\Concerns\InteractsWithToasts;
use Carbon\CarbonImmutable;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Detalle de una cita con sus acciones: atendida, no asistió, reprogramar,
 * cancelar y devolver la sesión. Cada acción vuelve a autorizar.
 */
class AppointmentPanel extends Component
{
    use InteractsWithToasts;

    #[Locked]
    public ?int $appointmentId = null;

    public bool $show = false;

    /** detail | reschedule | cancel | refund */
    public string $mode = 'detail';

    public string $cancelReason = '';

    public string $refundReason = '';

    public string $newDate = '';

    public string $newStaffId = '';

    public string $newSlot = '';

    public string $rescheduleReason = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Appointment::class);
    }

    #[On('open-appointment')]
    public function open(int $id): void
    {
        $appointment = $this->load($id);
        $this->authorize('view', $appointment);

        $this->appointmentId = $appointment->id;
        $this->mode = 'detail';
        $this->reset(['cancelReason', 'refundReason', 'newSlot', 'rescheduleReason']);
        $this->resetValidation();
        $this->show = true;
    }

    public function setMode(string $mode): void
    {
        if (! in_array($mode, ['detail', 'reschedule', 'cancel', 'refund'], true)) {
            return;
        }

        $appointment = $this->appointment();
        $this->authorize(match ($mode) {
            'reschedule' => 'update',
            'cancel' => 'cancel',
            'refund' => 'refundCredit',
            default => 'view',
        }, $appointment);

        if ($mode === 'reschedule') {
            $this->newDate = $appointment->starts_at->setTimezone($appointment->location->timezone ?: 'UTC')->toDateString();
            $this->newStaffId = (string) $appointment->staff_id;
            $this->newSlot = '';
        }

        $this->resetValidation();
        $this->mode = $mode;
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['newDate', 'newStaffId'], true)) {
            $this->newSlot = '';
        }
    }

    public function mark(string $status, MarkAppointment $mark): void
    {
        $appointment = $this->appointment();
        $this->authorize('update', $appointment);

        $to = AppointmentStatus::from($status);
        $mark->execute($appointment, $to, auth()->user());

        // Si es una cita clínica del profesional, el panel queda abierto con el enlace a la nota.
        if ($to === AppointmentStatus::Completed && $this->clinicalLink($appointment->fresh()) !== null) {
            $this->dispatch('appointments-changed');
            $this->toast('Cita marcada como atendida. Registre la nota clínica.');

            return;
        }

        $this->changed($to === AppointmentStatus::Completed ? 'Cita marcada como atendida.' : 'Inasistencia registrada.');
    }

    public function cancel(CancelAppointment $cancel): void
    {
        $appointment = $this->appointment();
        $this->authorize('cancel', $appointment);
        $this->validate(['cancelReason' => ['required', 'string', 'max:200']], [], ['cancelReason' => 'motivo']);

        $cancel->execute($appointment, $this->cancelReason, auth()->user());

        $this->changed('Cita cancelada.', 'warning');
    }

    public function reschedule(RescheduleAppointment $reschedule): void
    {
        $appointment = $this->appointment();
        $this->authorize('update', $appointment);

        $this->validate([
            'newDate' => ['required', 'date_format:Y-m-d'],
            'newSlot' => ['required', 'regex:/^\d+\|[0-9TZ:\-+]+$/'],
            'rescheduleReason' => ['nullable', 'string', 'max:200'],
        ], ['newSlot.required' => 'Elija un horario.']);

        [$staffId, $start] = explode('|', $this->newSlot, 2);
        $staff = Staff::query()->withoutGlobalScopes()->whereNull('deleted_at')->findOrFail((int) $staffId);
        $this->authorize('create', [Appointment::class, $appointment->location, $staff]);

        $reschedule->execute($appointment, $staff, CarbonImmutable::parse($start)->utc(), auth()->user(), $this->rescheduleReason ?: null);

        $this->changed('Cita reprogramada.');
    }

    public function refund(RefundAppointmentCredit $refund): void
    {
        $appointment = $this->appointment();
        $this->authorize('refundCredit', $appointment);
        $this->validate(['refundReason' => ['required', 'string', 'max:200']], [], ['refundReason' => 'motivo']);

        $refund->execute($appointment, $this->refundReason, auth()->user());

        $this->changed('Sesión devuelta al cliente.');
    }

    public function render(Availability $availability, CancelAppointment $cancel): View
    {
        $appointment = $this->appointmentId ? $this->load($this->appointmentId) : null;
        $slots = collect();
        $staffOptions = collect();

        if ($appointment && $this->mode === 'reschedule') {
            $staffOptions = $availability->eligibleStaff($appointment->service, $appointment->location);
            if (! auth()->user()->can('appointments.view-all')) {
                $staffOptions = $staffOptions->where('id', auth()->user()->staff?->id)->values();
            }
            $only = $staffOptions->firstWhere('id', (int) $this->newStaffId);
            if ($only !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->newDate)) {
                $slots = $availability->slots($appointment->service, $appointment->location, $this->newDate, $only, $appointment->id);
            }
        }

        return view('livewire.admin.appointments.appointment-panel', [
            'appointment' => $appointment,
            'tz' => $appointment?->location?->timezone ?: 'UTC',
            'late' => $appointment && $appointment->isActive() ? $cancel->isLate($appointment) : false,
            'invoice' => $appointment?->invoice(),
            'creditsUsed' => $appointment?->creditsUsed() ?? 0,
            'staffOptions' => $staffOptions,
            'freeSlots' => $slots,
            'started' => $appointment ? $appointment->starts_at->lessThanOrEqualTo(now()) : false,
            'clinicalLink' => $this->clinicalLink($appointment),
        ]);
    }

    /**
     * Enlace a la nota clínica para una cita clínica atendida del propio
     * profesional que aún no tiene sesión registrada.
     */
    private function clinicalLink(?Appointment $appointment): ?string
    {
        if ($appointment === null || $appointment->status !== AppointmentStatus::Completed || ! $appointment->service?->is_clinical) {
            return null;
        }

        $record = PhysiotherapyRecord::query()->where('member_id', $appointment->member_id)->first();
        if ($record === null || $appointment->physiotherapySession()->exists() || ! auth()->user()->can('write', $record)
            || auth()->user()->staff?->id !== $appointment->staff_id) {
            return null;
        }

        return route('admin.clinical.show', ['member' => $appointment->member_id, 'cita' => $appointment->id]);
    }

    private function changed(string $message, string $type = 'success'): void
    {
        $this->show = false;
        $this->dispatch('appointments-changed');
        $this->toast($message, $type);
    }

    private function appointment(): Appointment
    {
        return $this->load((int) $this->appointmentId);
    }

    private function load(int $id): Appointment
    {
        return Appointment::query()
            ->with(['member', 'staff', 'service.locations', 'location', 'room', 'creator:id,name', 'statusHistories.changer:id,name', 'rescheduledFrom'])
            ->findOrFail($id);
    }
}
