<?php

namespace App\Livewire\Admin\Locations;

use App\Domain\Locations\Actions\AddLocationClosure;
use App\Domain\Locations\Actions\RemoveLocationClosure;
use App\Domain\Locations\Models\Location;
use App\Domain\Locations\Models\LocationClosure;
use App\Livewire\Admin\Concerns\InteractsWithToasts;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Festivos y cierres puntuales. Un cierre global (sin sede) afecta a todas
 * las sedes y solo lo gestiona quien ve todas.
 */
class LocationClosures extends Component
{
    use InteractsWithToasts;

    #[Locked]
    public int $locationId;

    public string $closedOn = '';

    public string $reason = '';

    public bool $allLocations = false;

    public bool $showForm = false;

    public function mount(int $locationId): void
    {
        $this->locationId = $locationId;
        $this->authorize('view', $this->location());
    }

    public function create(): void
    {
        $this->authorize('manageClosures', $this->location());
        $this->reset(['closedOn', 'reason', 'allLocations']);
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(AddLocationClosure $add): void
    {
        $location = $this->location();
        $this->authorize('manageClosures', $location);

        if ($this->allLocations) {
            $this->authorize('manageGlobalClosures', Location::class);
        }

        $data = $this->validate([
            'closedOn' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'reason' => ['nullable', 'string', 'max:150'],
            'allLocations' => ['boolean'],
        ], [], ['closedOn' => 'fecha', 'reason' => 'motivo']);

        $add->execute($this->allLocations ? null : $location->id, $data['closedOn'], $data['reason'] ?: null);

        $this->showForm = false;
        $this->toast('Cierre registrado.');
    }

    public function remove(int $closureId, RemoveLocationClosure $remove): void
    {
        $location = $this->location();
        $closure = LocationClosure::query()->affecting($location->id)->findOrFail($closureId);

        $closure->isGlobal()
            ? $this->authorize('manageGlobalClosures', Location::class)
            : $this->authorize('manageClosures', $location);

        $remove->execute($closure);
        $this->toast('Cierre eliminado.');
    }

    public function render(): View
    {
        $location = $this->location();
        $user = auth()->user();

        return view('livewire.admin.locations.closures', [
            'location' => $location,
            'closures' => LocationClosure::query()
                ->affecting($location->id)
                ->whereDate('closed_on', '>=', now()->subMonths(1)->toDateString())
                ->orderBy('closed_on')
                ->get(),
            'canManage' => $user->can('manageClosures', $location),
            'canManageGlobal' => $user->can('manageGlobalClosures', Location::class),
        ]);
    }

    private function location(): Location
    {
        return Location::query()->findOrFail($this->locationId);
    }
}
