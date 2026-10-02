<?php

namespace App\Livewire\Admin\Staff;

use App\Domain\Identity\Enums\RoleName;
use App\Domain\Locations\Models\Location;
use App\Domain\Staff\Actions\SendStaffInvitation;
use App\Domain\Staff\Actions\ToggleStaffStatus;
use App\Domain\Staff\Enums\StaffStatus;
use App\Domain\Staff\Models\Staff;
use App\Livewire\Admin\Concerns\InteractsWithToasts;
use App\Support\Locations\CurrentLocation;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Staff')]
class StaffIndex extends Component
{
    use InteractsWithToasts, WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $role = '';

    #[Url]
    public string $location = '';

    #[Url]
    public string $status = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Staff::class);
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['search', 'role', 'location', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function toggleStatus(int $staffId, ToggleStaffStatus $toggle): void
    {
        $staff = Staff::query()->findOrFail($staffId);
        $this->authorize('toggleStatus', $staff);

        $toggle->execute($staff, auth()->user());

        $this->toast($staff->isActive() ? "{$staff->full_name} está activo." : "{$staff->full_name} quedó inactivo.");
    }

    public function resendInvitation(int $staffId, SendStaffInvitation $send): void
    {
        $staff = Staff::query()->findOrFail($staffId);
        $this->authorize('resendInvitation', $staff);

        $send->execute($staff, auth()->user());

        $this->toast("Invitación reenviada a {$staff->full_name}.");
    }

    public function render(CurrentLocation $current): View
    {
        $this->authorize('viewAny', Staff::class);

        $filters = validator(
            ['role' => $this->role, 'status' => $this->status, 'location' => $this->location],
            [
                'role' => ['nullable', Rule::enum(RoleName::class)],
                'status' => ['nullable', Rule::enum(StaffStatus::class)],
                'location' => ['nullable', 'integer'],
            ],
        )->valid();

        // Filtro de sede: el de la tabla tiene prioridad sobre el selector superior.
        $locationFilter = ($filters['location'] ?? '') !== '' ? (int) $filters['location'] : $current->id();

        $staff = Staff::query()
            ->with(['user.roles', 'locations'])
            ->search($this->search)
            ->inLocation($locationFilter)
            ->when(($filters['role'] ?? '') !== '', fn ($q) => $q->whereHas('user.roles', fn ($r) => $r->where('name', $filters['role'])))
            ->when(($filters['status'] ?? '') !== '', fn ($q) => $q->where('status', $filters['status']))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(15);

        return view('livewire.admin.staff.index', [
            'staff' => $staff,
            'roles' => collect(RoleName::staffRoles())->mapWithKeys(fn (RoleName $r) => [$r->value => $r->label()])->all(),
            'locations' => Location::query()->orderBy('name')->pluck('name', 'id')->all(),
            'statuses' => StaffStatus::options(),
        ]);
    }
}
