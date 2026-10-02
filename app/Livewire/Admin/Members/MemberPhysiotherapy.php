<?php

namespace App\Livewire\Admin\Members;

use App\Domain\Physiotherapy\Actions\OpenPhysiotherapyRecord;
use App\Domain\Physiotherapy\Models\PhysiotherapyRecord;
use App\Livewire\Admin\Concerns\InteractsWithToasts;
use App\Livewire\Admin\Members\Concerns\ResolvesMember;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Pestaña "Fisioterapia": datos administrativos del expediente (sin
 * contenido clínico) y apertura de la historia.
 */
class MemberPhysiotherapy extends Component
{
    use InteractsWithToasts;
    use ResolvesMember {
        mount as resolveMember;
    }

    public bool $showOpen = false;

    public string $reason = '';

    public string $history = '';

    public string $medications = '';

    public string $allergies = '';

    public function mount(int $memberId): void
    {
        $this->resolveMember($memberId);
        $this->authorize('viewSummary', [PhysiotherapyRecord::class, $this->member()]);
    }

    public function openForm(): void
    {
        $this->authorize('open', [PhysiotherapyRecord::class, $this->member()]);
        $this->reset(['reason', 'history', 'medications', 'allergies']);
        $this->resetValidation();
        $this->showOpen = true;
    }

    public function open(OpenPhysiotherapyRecord $open): void
    {
        $member = $this->member();
        $this->authorize('open', [PhysiotherapyRecord::class, $member]);

        $this->validate([
            'reason' => ['nullable', 'string', 'max:5000'],
            'history' => ['nullable', 'string', 'max:20000'],
            'medications' => ['nullable', 'string', 'max:5000'],
            'allergies' => ['nullable', 'string', 'max:5000'],
        ]);

        $open->execute($member, auth()->user(), [
            'reason_for_consultation' => $this->reason,
            'medical_history' => $this->history,
            'medications' => $this->medications,
            'allergies' => $this->allergies,
        ]);

        $this->redirectRoute('admin.clinical.show', $member, navigate: true);
    }

    public function render(): View
    {
        $member = $this->member();
        $record = PhysiotherapyRecord::query()->where('member_id', $member->id)
            ->with(['primaryStaff:id,first_name,last_name', 'activeTeam.staff:id,first_name,last_name'])
            ->withCount('sessions')
            ->first();

        return view('livewire.admin.members.physiotherapy', [
            'member' => $member,
            'record' => $record,
            'lastSession' => $record?->sessions()->value('performed_at'),
            'canOpenRecord' => $record !== null && (auth()->user()->can('view', $record) || auth()->user()->can('emergency', $record)),
            'canCreate' => $record === null && auth()->user()->can('open', [PhysiotherapyRecord::class, $member]),
        ]);
    }
}
