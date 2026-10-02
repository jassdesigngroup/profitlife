<?php

namespace App\Livewire\Admin\Locations;

use App\Domain\Locations\Actions\SyncLocationHours;
use App\Domain\Locations\DTOs\HourSlot;
use App\Domain\Locations\Enums\DayOfWeek;
use App\Domain\Locations\Models\Location;
use App\Domain\Locations\Models\LocationHour;
use App\Livewire\Admin\Concerns\InteractsWithToasts;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Horario semanal: varias franjas por día (jornada partida).
 */
class LocationHours extends Component
{
    use InteractsWithToasts;

    #[Locked]
    public int $locationId;

    /** @var list<array{day_of_week: int|string, opens_at: string, closes_at: string}> */
    public array $slots = [];

    public bool $editing = false;

    public function mount(int $locationId): void
    {
        $this->locationId = $locationId;
        $this->authorize('view', $this->location());
        $this->loadSlots();
    }

    public function edit(): void
    {
        $this->authorize('manageHours', $this->location());
        $this->loadSlots();
        $this->editing = true;
    }

    public function cancel(): void
    {
        $this->resetValidation();
        $this->loadSlots();
        $this->editing = false;
    }

    public function addSlot(int $day = 1): void
    {
        $this->authorize('manageHours', $this->location());
        $this->slots[] = ['day_of_week' => $day, 'opens_at' => '06:00', 'closes_at' => '12:00'];
    }

    public function removeSlot(int $index): void
    {
        $this->authorize('manageHours', $this->location());
        unset($this->slots[$index]);
        $this->slots = array_values($this->slots);
    }

    public function save(SyncLocationHours $sync): void
    {
        $location = $this->location();
        $this->authorize('manageHours', $location);

        $this->validate([
            'slots' => ['array', 'max:50'],
            'slots.*.day_of_week' => ['required', 'integer', Rule::enum(DayOfWeek::class)],
            'slots.*.opens_at' => ['required', 'date_format:H:i'],
            'slots.*.closes_at' => ['required', 'date_format:H:i'],
        ], [], [
            'slots.*.day_of_week' => 'día',
            'slots.*.opens_at' => 'apertura',
            'slots.*.closes_at' => 'cierre',
        ]);

        $sync->execute($location, array_map(fn (array $s) => new HourSlot(
            DayOfWeek::from((int) $s['day_of_week']),
            $s['opens_at'],
            $s['closes_at'],
        ), $this->slots), auth()->user());

        $this->editing = false;
        $this->loadSlots();
        $this->toast('Horario actualizado.');
    }

    public function render(): View
    {
        $location = $this->location();

        return view('livewire.admin.locations.hours', [
            'location' => $location,
            'byDay' => $location->hours()->get()->groupBy(fn (LocationHour $h) => $h->day_of_week->value),
            'days' => DayOfWeek::cases(),
            'canManage' => auth()->user()->can('manageHours', $location),
        ]);
    }

    private function location(): Location
    {
        return Location::query()->findOrFail($this->locationId);
    }

    private function loadSlots(): void
    {
        $this->slots = $this->location()->hours()->get()->map(fn (LocationHour $h) => [
            'day_of_week' => $h->day_of_week->value,
            'opens_at' => substr($h->opens_at, 0, 5),
            'closes_at' => substr($h->closes_at, 0, 5),
        ])->all();
    }
}
