<?php

namespace App\Livewire\Admin\Clinical;

use App\Domain\Appointments\Enums\AppointmentStatus;
use App\Domain\Appointments\Models\Appointment;
use App\Domain\Documents\Models\Document;
use App\Domain\Members\Models\Member;
use App\Domain\Physiotherapy\Actions\AddClinicalAddendum;
use App\Domain\Physiotherapy\Actions\GrantEmergencyAccess;
use App\Domain\Physiotherapy\Actions\RecordPhysiotherapySession;
use App\Domain\Physiotherapy\Actions\SaveTreatmentPlan;
use App\Domain\Physiotherapy\Actions\SignClinicalNote;
use App\Domain\Physiotherapy\Actions\UpdateClinicalNote;
use App\Domain\Physiotherapy\Actions\UpdatePhysiotherapyRecord;
use App\Domain\Physiotherapy\Enums\ClinicalAction;
use App\Domain\Physiotherapy\Enums\PlanStatus;
use App\Domain\Physiotherapy\Enums\RecordStatus;
use App\Domain\Physiotherapy\Enums\SessionType;
use App\Domain\Physiotherapy\Models\ClinicalNote;
use App\Domain\Physiotherapy\Models\PhysiotherapyRecord;
use App\Domain\Physiotherapy\Models\TreatmentPlan;
use App\Domain\Physiotherapy\Services\ClinicalAccess;
use App\Domain\Settings\Services\Settings;
use App\Livewire\Admin\Concerns\InteractsWithToasts;
use App\Support\Locations\CurrentLocation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Historia clínica de fisioterapia. Solo el equipo tratante ve el contenido
 * (o quien abrió un acceso de emergencia). Cada apertura queda en la
 * bitácora clínica; cada acción vuelve a autorizar.
 */
class ClinicalRecordShow extends Component
{
    use InteractsWithToasts;

    #[Locked]
    public int $memberId;

    #[Locked]
    public ?int $recordId = null;

    #[Url(as: 'cita')]
    public string $appointmentParam = '';

    // Antecedentes
    public bool $showRecord = false;

    public string $reason = '';

    public string $history = '';

    public string $medications = '';

    public string $allergies = '';

    public string $recordStatus = 'active';

    // Plan
    public bool $showPlan = false;

    #[Locked]
    public ?int $planId = null;

    public string $planTitle = '';

    public string $planDiagnosis = '';

    public string $planGoals = '';

    public string $planSessions = '';

    public string $planStartsOn = '';

    public string $planEndsOn = '';

    public string $planStatus = 'active';

    public bool $planVisible = false;

    // Sesión y nota
    public bool $showSession = false;

    #[Locked]
    public ?int $noteId = null;

    public string $sessionType = 'treatment';

    public string $performedAt = '';

    public string $painScale = '';

    public string $sessionPlanId = '';

    public string $sessionAppointmentId = '';

    public string $summaryForMember = '';

    /** @var array<string, string> */
    public array $sections = [];

    public string $signPassword = '';

    // Firma de una nota guardada
    public bool $showSign = false;

    // Adenda
    public bool $showAddendum = false;

    public string $addendumText = '';

    public string $emergencyReason = '';

    public function mount(Member $member, ClinicalAccess $access): void
    {
        $this->memberId = $member->id;
        $record = PhysiotherapyRecord::query()->where('member_id', $member->id)->firstOrFail();
        $this->recordId = $record->id;

        $user = auth()->user();
        if ($user->can('view', $record)) {
            $access->log($user, $member->id, $record, ClinicalAction::View);

            if ($this->appointmentParam !== '' && ctype_digit($this->appointmentParam) && $user->can('write', $record)) {
                $this->newSession((int) $this->appointmentParam);
            }

            return;
        }

        abort_unless($user->can('emergency', $record), 403);
    }

    public function grantEmergency(GrantEmergencyAccess $grant, ClinicalAccess $access): void
    {
        $record = $this->record();
        $this->authorize('emergency', $record);
        $this->validate(['emergencyReason' => ['required', 'string', 'min:10', 'max:300']], [], ['emergencyReason' => 'motivo']);

        $grant->execute($record, auth()->user(), $this->emergencyReason);
        $access->log(auth()->user(), $record->member_id, $record, ClinicalAction::View);
        $this->emergencyReason = '';
        $this->toast('Acceso de emergencia abierto por '.ClinicalAccess::EMERGENCY_HOURS.' horas. Quedó registrado.', 'warning');
    }

    public function editRecord(): void
    {
        $record = $this->record();
        $this->authorize('update', $record);

        $this->reason = (string) $record->reason_for_consultation;
        $this->history = (string) $record->medical_history;
        $this->medications = (string) $record->medications;
        $this->allergies = (string) $record->allergies;
        $this->recordStatus = $record->status->value;
        $this->resetValidation();
        $this->showRecord = true;
    }

    public function saveRecord(UpdatePhysiotherapyRecord $update): void
    {
        $record = $this->record();
        $this->authorize('update', $record);

        $this->validate([
            'reason' => ['nullable', 'string', 'max:5000'],
            'history' => ['nullable', 'string', 'max:20000'],
            'medications' => ['nullable', 'string', 'max:5000'],
            'allergies' => ['nullable', 'string', 'max:5000'],
            'recordStatus' => ['required', Rule::enum(RecordStatus::class)],
        ]);

        $update->execute($record, auth()->user(), [
            'reason_for_consultation' => $this->reason,
            'medical_history' => $this->history,
            'medications' => $this->medications,
            'allergies' => $this->allergies,
            'status' => RecordStatus::from($this->recordStatus),
        ]);

        $this->showRecord = false;
        $this->toast('Antecedentes guardados.');
    }

    public function editPlan(?int $planId = null): void
    {
        $record = $this->record();
        $this->authorize('write', $record);
        $this->resetValidation();

        $plan = $planId ? TreatmentPlan::query()->where('physiotherapy_record_id', $record->id)->findOrFail($planId) : null;
        $this->planId = $plan?->id;
        $this->planTitle = $plan->title ?? '';
        $this->planDiagnosis = (string) $plan?->diagnosis;
        $this->planGoals = (string) $plan?->goals;
        $this->planSessions = $plan?->planned_sessions ? (string) $plan->planned_sessions : '';
        $this->planStartsOn = $plan?->starts_on?->toDateString() ?? now(app(Settings::class)->displayTimezone())->toDateString();
        $this->planEndsOn = $plan?->ends_on?->toDateString() ?? '';
        $this->planStatus = $plan?->status->value ?? PlanStatus::Active->value;
        $this->planVisible = (bool) $plan?->is_visible_to_member;
        $this->showPlan = true;
    }

    public function savePlan(SaveTreatmentPlan $save): void
    {
        $record = $this->record();
        $this->authorize('write', $record);

        $this->validate([
            'planTitle' => ['required', 'string', 'max:150'],
            'planDiagnosis' => ['nullable', 'string', 'max:5000'],
            'planGoals' => ['nullable', 'string', 'max:5000'],
            'planSessions' => ['nullable', 'integer', 'min:1', 'max:200'],
            'planStartsOn' => ['required', 'date_format:Y-m-d'],
            'planEndsOn' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:planStartsOn'],
            'planStatus' => ['required', Rule::enum(PlanStatus::class)],
            'planVisible' => ['boolean'],
        ], [], ['planTitle' => 'título', 'planSessions' => 'sesiones', 'planStartsOn' => 'inicio', 'planEndsOn' => 'fin']);

        $plan = $this->planId ? TreatmentPlan::query()->where('physiotherapy_record_id', $record->id)->findOrFail($this->planId) : null;

        $save->execute($record, $plan, auth()->user(), [
            'title' => $this->planTitle,
            'diagnosis' => $this->planDiagnosis,
            'goals' => $this->planGoals,
            'planned_sessions' => $this->planSessions === '' ? null : (int) $this->planSessions,
            'starts_on' => CarbonImmutable::createFromFormat('!Y-m-d', $this->planStartsOn),
            'ends_on' => $this->planEndsOn === '' ? null : CarbonImmutable::createFromFormat('!Y-m-d', $this->planEndsOn),
            'status' => PlanStatus::from($this->planStatus),
            'is_visible_to_member' => $this->planVisible,
        ]);

        $this->showPlan = false;
        $this->toast('Plan de tratamiento guardado.');
    }

    public function newSession(?int $appointmentId = null): void
    {
        $record = $this->record();
        $this->authorize('write', $record);
        $this->resetValidation();

        $hasSessions = $record->sessions()->exists();
        $this->noteId = null;
        $this->sessionType = $hasSessions ? SessionType::Treatment->value : SessionType::InitialEvaluation->value;
        $this->performedAt = now(app(Settings::class)->displayTimezone())->format('Y-m-d\TH:i');
        $this->reset(['painScale', 'summaryForMember', 'signPassword', 'sessionAppointmentId']);
        $this->sections = [];
        $this->sessionPlanId = (string) ($record->plans()->where('status', PlanStatus::Active)->value('id') ?? '');

        if ($appointmentId !== null) {
            $appointment = $this->appointmentOptions()->firstWhere('id', $appointmentId);
            if ($appointment !== null) {
                $this->sessionAppointmentId = (string) $appointment->id;
                $this->performedAt = $appointment->starts_at->setTimezone(app(Settings::class)->displayTimezone())->format('Y-m-d\TH:i');
            }
        }

        $this->showSession = true;
    }

    public function editNote(int $noteId): void
    {
        $note = $this->note($noteId);
        $this->authorize('update', $note);
        $this->resetValidation();

        $this->noteId = $note->id;
        $this->sessionType = $note->session?->session_type->value ?? SessionType::Treatment->value;
        $this->painScale = $note->session?->pain_scale === null ? '' : (string) $note->session->pain_scale;
        $this->summaryForMember = (string) $note->session?->summary_for_member;
        $this->sections = array_map('strval', $note->body ?? []);
        $this->signPassword = '';
        $this->showSession = true;
    }

    public function saveSession(RecordPhysiotherapySession $record, UpdateClinicalNote $update, SignClinicalNote $sign, CurrentLocation $current, bool $andSign = false): void
    {
        $clinical = $this->record();
        $this->authorize('write', $clinical);

        $this->validate([
            'sessionType' => ['required', Rule::enum(SessionType::class)],
            'performedAt' => [$this->noteId ? 'nullable' : 'required', 'date_format:Y-m-d\TH:i'],
            'painScale' => ['nullable', 'integer', 'between:0,10'],
            'sessionPlanId' => ['nullable', 'integer'],
            'sessionAppointmentId' => ['nullable', 'integer'],
            'summaryForMember' => ['nullable', 'string', 'max:2000'],
            'sections' => ['array'],
            'sections.*' => ['nullable', 'string', 'max:20000'],
            'signPassword' => [$andSign ? 'required' : 'nullable', 'string'],
        ], [], ['performedAt' => 'fecha', 'painScale' => 'dolor', 'signPassword' => 'contraseña']);

        $user = auth()->user();

        if ($this->noteId !== null) {
            $note = $this->note($this->noteId);
            $this->authorize('update', $note);
            $update->execute($note, $user, $this->sections, [
                'pain_scale' => $this->painScale === '' ? null : (int) $this->painScale,
                'summary_for_member' => $this->summaryForMember,
            ]);
        } else {
            $appointment = $this->sessionAppointmentId !== '' ? $this->appointmentOptions()->firstWhere('id', (int) $this->sessionAppointmentId) : null;
            $tz = app(Settings::class)->displayTimezone();

            $note = $record->execute($clinical, $user, [
                'session_type' => SessionType::from($this->sessionType),
                'performed_at' => CarbonImmutable::createFromFormat('Y-m-d\TH:i', $this->performedAt, $tz)->utc(),
                'pain_scale' => $this->painScale === '' ? null : (int) $this->painScale,
                'treatment_plan_id' => $this->sessionPlanId === '' ? null : (int) $this->sessionPlanId,
                'appointment_id' => $appointment?->id,
                'location_id' => $appointment?->location_id ?? $current->id() ?? $clinical->member->home_location_id,
                'summary_for_member' => $this->summaryForMember,
                'sections' => $this->sections,
            ]);
        }

        if ($andSign) {
            $this->authorize('sign', $note->fresh());
            $sign->execute($note->fresh(), $user, $this->signPassword);
        }

        $this->signPassword = '';
        $this->showSession = false;
        $this->appointmentParam = '';
        $this->toast($andSign ? 'Nota guardada y firmada.' : 'Nota guardada sin firmar.');
    }

    public function openSign(int $noteId): void
    {
        $note = $this->note($noteId);
        $this->authorize('sign', $note);
        $this->noteId = $note->id;
        $this->signPassword = '';
        $this->resetValidation();
        $this->showSign = true;
    }

    public function sign(SignClinicalNote $sign): void
    {
        $note = $this->note((int) $this->noteId);
        $this->authorize('sign', $note);
        $this->validate(['signPassword' => ['required', 'string']], [], ['signPassword' => 'contraseña']);

        $sign->execute($note, auth()->user(), $this->signPassword);

        $this->signPassword = '';
        $this->showSign = false;
        $this->toast('Nota firmada.');
    }

    public function openAddendum(int $noteId): void
    {
        $note = $this->note($noteId);
        $this->authorize('addAddendum', $note);
        $this->noteId = $note->id;
        $this->reset(['addendumText', 'signPassword']);
        $this->resetValidation();
        $this->showAddendum = true;
    }

    public function addAddendum(AddClinicalAddendum $add): void
    {
        $note = $this->note((int) $this->noteId);
        $this->authorize('addAddendum', $note);
        $this->validate([
            'addendumText' => ['required', 'string', 'max:10000'],
            'signPassword' => ['required', 'string'],
        ], [], ['addendumText' => 'adenda', 'signPassword' => 'contraseña']);

        $add->execute($note, auth()->user(), $this->addendumText, $this->signPassword);

        $this->reset(['addendumText', 'signPassword']);
        $this->showAddendum = false;
        $this->toast('Adenda firmada.');
    }

    public function render(ClinicalAccess $access): View
    {
        $record = $this->record()->load(['member.homeLocation:id,name', 'primaryStaff', 'activeTeam.staff']);
        $user = auth()->user();
        $member = $record->member;

        if (! $user->can('view', $record)) {
            abort_unless($user->can('emergency', $record), 403);

            return view('livewire.admin.clinical.emergency', ['record' => $record, 'member' => $member])
                ->title('Historia clínica · '.$member->full_name);
        }

        $sessions = $record->sessions()->with([
            'staff:id,first_name,last_name', 'plan:id,title', 'location:id,name',
            'notes' => fn ($q) => $q->whereNull('parent_note_id'),
            'notes.addenda.author:id,first_name,last_name', 'notes.author:id,first_name,last_name', 'notes.signer:id,first_name,last_name',
        ])->get();

        $pain = $sessions->whereNotNull('pain_scale')->sortBy('performed_at')->values();

        return view('livewire.admin.clinical.show', [
            'record' => $record,
            'member' => $member,
            'sessions' => $sessions,
            'plans' => $record->plans()->with('staff:id,first_name,last_name')->withCount('sessions')->get(),
            'pain' => $pain,
            'hasConsent' => SignClinicalNote::hasClinicalConsent($member->id),
            'emergencyUntil' => $access->isOnTeam($user, $record) ? null : $access->emergencyExpiresAt($user, $member->id),
            'canWrite' => $user->can('write', $record),
            'myStaffId' => $user->staff?->id,
            'documents' => Document::query()->where('member_id', $member->id)->get()->filter->isClinical()->values(),
            'sessionTypes' => SessionType::options(),
            'planStatuses' => PlanStatus::options(),
            'recordStatuses' => RecordStatus::options(),
            'activePlans' => $record->plans()->whereIn('status', [PlanStatus::Active, PlanStatus::Draft])->pluck('title', 'id')->all(),
            'appointments' => $this->showSession && $this->noteId === null ? $this->appointmentOptions() : collect(),
            'templateSections' => SessionType::from($this->sessionType ?: 'treatment')->noteType()->sections(),
            'tz' => app(Settings::class)->displayTimezone(),
        ])->title('Historia clínica · '.$member->full_name);
    }

    /**
     * Citas clínicas del paciente con el profesional, sin sesión registrada.
     *
     * @return Collection<int, Appointment>
     */
    private function appointmentOptions()
    {
        $staffId = auth()->user()->staff?->id;

        return Appointment::query()->withoutGlobalScopes()
            ->where('member_id', $this->memberId)
            ->where('staff_id', $staffId)
            ->whereIn('status', [AppointmentStatus::Confirmed->value, AppointmentStatus::Completed->value])
            ->where('starts_at', '<=', now())
            ->whereHas('service', fn ($q) => $q->where('is_clinical', true))
            ->whereDoesntHave('physiotherapySession')
            ->with('service:id,name')
            ->latest('starts_at')
            ->limit(20)
            ->get();
    }

    private function record(): PhysiotherapyRecord
    {
        return PhysiotherapyRecord::query()->findOrFail((int) $this->recordId);
    }

    private function note(int $id): ClinicalNote
    {
        return ClinicalNote::query()->where('physiotherapy_record_id', $this->recordId)->with('record', 'session')->findOrFail($id);
    }
}
