<?php

namespace App\Livewire\Admin\Members;

use App\Domain\Locations\Models\Location;
use App\Domain\Members\Enums\MemberStatus;
use App\Domain\Members\Models\Member;
use App\Support\Locations\CurrentLocation;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Clientes')]
class MemberIndex extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $location = '';

    #[Url]
    public string $status = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Member::class);
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['search', 'location', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function render(CurrentLocation $current): View
    {
        $this->authorize('viewAny', Member::class);

        $filters = validator(
            ['status' => $this->status, 'location' => $this->location],
            ['status' => ['nullable', Rule::enum(MemberStatus::class)], 'location' => ['nullable', 'integer']],
        )->valid();

        $locationFilter = ($filters['location'] ?? '') !== '' ? (int) $filters['location'] : $current->id();

        $members = Member::query()
            ->with('homeLocation:id,name')
            ->search($this->search)
            ->inLocation($locationFilter)
            ->when(($filters['status'] ?? '') !== '', fn ($q) => $q->where('status', $filters['status']))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(20);

        return view('livewire.admin.members.index', [
            'members' => $members,
            'locations' => Location::query()->orderBy('name')->pluck('name', 'id')->all(),
            'statuses' => MemberStatus::options(),
        ]);
    }
}
