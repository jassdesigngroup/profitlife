<?php

namespace App\Livewire\Admin\Layout;

use App\Domain\Locations\Models\Location;
use App\Support\Locations\CurrentLocation;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Selector "Sede / Todas las sedes". Solo filtra la interfaz: la sede
 * elegida se valida contra el alcance del usuario al guardarla y al leerla.
 */
class LocationSwitcher extends Component
{
    public string $locationId = '';

    public function mount(CurrentLocation $current): void
    {
        $this->locationId = (string) ($current->id() ?? '');
    }

    public function updatedLocationId(string $value, CurrentLocation $current): void
    {
        $id = $value === '' ? null : (int) $value;

        abort_if($id !== null && ! auth()->user()->canAccessLocation($id), 403);

        $current->set($id);

        $this->redirect(url()->previous(route('admin.dashboard')), navigate: true);
    }

    /**
     * @return Collection<int, Location>
     */
    #[Computed]
    public function locations(): Collection
    {
        // LocationScope ya limita a las sedes del usuario.
        return Location::query()->orderBy('name')->get(['id', 'name', 'is_active']);
    }

    #[Computed]
    public function canSeeAll(): bool
    {
        return auth()->user()->canAccessAllLocations() || $this->locations->count() > 1;
    }

    public function render(): View
    {
        return view('livewire.admin.layout.location-switcher');
    }
}
