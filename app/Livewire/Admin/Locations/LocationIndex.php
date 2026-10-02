<?php

namespace App\Livewire\Admin\Locations;

use App\Domain\Locations\Actions\ToggleLocationStatus;
use App\Domain\Locations\Models\Location;
use App\Livewire\Admin\Concerns\InteractsWithToasts;
use App\Support\Locations\CurrentLocation;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Sedes')]
class LocationIndex extends Component
{
    use InteractsWithToasts, WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $status = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Location::class);
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['search', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function toggleStatus(int $locationId, ToggleLocationStatus $toggle): void
    {
        // findOrFail aplica LocationScope: una sede ajena no se encuentra.
        $location = Location::query()->findOrFail($locationId);
        $this->authorize('toggleStatus', $location);

        $toggle->execute($location);

        $this->toast($location->is_active ? "{$location->name} está activa." : "{$location->name} quedó inactiva.");
    }

    public function render(CurrentLocation $current): View
    {
        $this->authorize('viewAny', Location::class);

        $term = trim($this->search);

        $locations = Location::query()
            ->inLocation($current->id())
            ->withCount(['rooms', 'staff'])
            ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%")
                ->orWhere('city', 'like', "%{$term}%")))
            ->when($this->status !== '', fn ($q) => $q->where('is_active', $this->status === 'active'))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.admin.locations.index', ['locations' => $locations]);
    }
}
