<?php

namespace App\Livewire\Admin\Clinical;

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Physiotherapy\Models\PhysiotherapyRecord;
use App\Domain\Physiotherapy\Services\ClinicalTeam as TeamService;
use App\Domain\Staff\Enums\StaffStatus;
use App\Domain\Staff\Models\Staff;
use App\Livewire\Admin\Concerns\InteractsWithToasts;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Equipo tratante del expediente. Lo ve quien puede ver el resumen; lo
 * gestiona el responsable o quien administra staff. No muestra contenido clínico.
 */
class ClinicalTeam extends Component
{
    use InteractsWithToasts;

    #[Locked]
    public int $recordId;

    public string $newStaffId = '';

    public function mount(int $recordId): void
    {
        $this->recordId = $recordId;
        $this->authorize('viewSummary', [PhysiotherapyRecord::class, $this->record()->member]);
    }

    public function add(TeamService $team, AuditLogger $audit): void
    {
        $record = $this->record();
        $this->authorize('manageTeam', $record);
        $this->validate(['newStaffId' => ['required', 'integer', Rule::in($this->candidates($record)->pluck('id')->all())]], [], ['newStaffId' => 'profesional']);

        $staff = Staff::query()->withoutGlobalScopes()->findOrFail((int) $this->newStaffId);
        $team->add($record, $staff, auth()->user());
        $audit->log('clinical', AuditEvent::ClinicalTeamChanged, $record, auth()->user(), ['member_id' => $record->member_id, 'added_staff_id' => $staff->id]);

        $this->newStaffId = '';
        $this->toast("{$staff->full_name} se agregó al equipo tratante.");
    }

    public function revoke(int $staffId, TeamService $team, AuditLogger $audit): void
    {
        $record = $this->record();
        $this->authorize('manageTeam', $record);

        if ($staffId === $record->primary_staff_id) {
            $this->toast('El responsable no se puede retirar del equipo.', 'warning');

            return;
        }

        $staff = Staff::query()->withoutGlobalScopes()->findOrFail($staffId);
        if ($team->revoke($record, $staff)) {
            $audit->log('clinical', AuditEvent::ClinicalTeamChanged, $record, auth()->user(), ['member_id' => $record->member_id, 'revoked_staff_id' => $staff->id]);
        }

        $this->toast("{$staff->full_name} ya no tiene acceso a esta historia.", 'warning');
    }

    public function render(): View
    {
        $record = $this->record()->load('activeTeam.staff', 'primaryStaff');

        return view('livewire.admin.clinical.team', [
            'record' => $record,
            'canManage' => auth()->user()->can('manageTeam', $record),
            'candidates' => auth()->user()->can('manageTeam', $record) ? $this->candidates($record)->pluck('full_name', 'id')->all() : [],
        ]);
    }

    /**
     * Profesionales activos con permisos clínicos que aún no están en el equipo.
     *
     * @return Collection<int, Staff>
     */
    private function candidates(PhysiotherapyRecord $record)
    {
        $current = $record->activeTeam()->pluck('staff_id');

        return Staff::query()->withoutGlobalScopes()->whereNull('deleted_at')
            ->where('status', StaffStatus::Active)
            ->whereNotIn('id', $current)
            ->whereHas('user', fn ($q) => $q->permission(Permission::ClinicalNotesView->value))
            ->orderBy('first_name')
            ->get();
    }

    private function record(): PhysiotherapyRecord
    {
        return PhysiotherapyRecord::query()->findOrFail($this->recordId);
    }
}
