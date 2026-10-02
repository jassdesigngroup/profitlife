<?php

namespace App\Livewire\Admin\Locations;

use App\Domain\Locations\Models\Location;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Ficha de la sede con pestañas. Cada pestaña es un componente que se
 * autoriza por sí mismo.
 */
class LocationShow extends Component
{
    #[Locked]
    public int $locationId;

    #[Url]
    public string $tab = 'hours';

    public function mount(Location $location): void
    {
        $this->authorize('view', $location);
        $this->locationId = $location->id;

        if (! in_array($this->tab, ['hours', 'closures', 'rooms', 'kiosks'], true)) {
            $this->tab = 'hours';
        }
    }

    public function render(): View
    {
        $location = Location::query()->withCount(['rooms', 'staff'])->findOrFail($this->locationId);
        $this->authorize('view', $location);

        return view('livewire.admin.locations.show', ['location' => $location])->title($location->name);
    }
}
