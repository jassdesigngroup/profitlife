<?php

namespace App\Livewire\Admin\Locations;

use App\Domain\Locations\Actions\CreateRoom;
use App\Domain\Locations\Actions\DeleteRoom;
use App\Domain\Locations\Actions\UpdateRoom;
use App\Domain\Locations\DTOs\RoomData;
use App\Domain\Locations\Enums\RoomType;
use App\Domain\Locations\Models\Location;
use App\Domain\Locations\Models\Room;
use App\Livewire\Admin\Concerns\InteractsWithToasts;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class LocationRooms extends Component
{
    use InteractsWithToasts;

    #[Locked]
    public int $locationId;

    #[Locked]
    public ?int $roomId = null;

    public string $name = '';

    public string $type = '';

    public int|string $capacity = 1;

    public bool $isActive = true;

    public bool $showForm = false;

    public function mount(int $locationId): void
    {
        $this->locationId = $locationId;
        $this->authorize('view', $this->location());
        $this->authorize('viewAny', Room::class);
    }

    public function create(): void
    {
        $this->authorize('create', [Room::class, $this->location()]);
        $this->reset(['roomId', 'name', 'type', 'capacity', 'isActive']);
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $roomId): void
    {
        $room = $this->room($roomId);
        $this->authorize('update', $room);

        $this->roomId = $room->id;
        $this->name = $room->name;
        $this->type = $room->type?->value ?? '';
        $this->capacity = $room->capacity;
        $this->isActive = $room->is_active;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(CreateRoom $create, UpdateRoom $update): void
    {
        $location = $this->location();
        $room = $this->roomId !== null ? $this->room($this->roomId) : null;
        $room ? $this->authorize('update', $room) : $this->authorize('create', [Room::class, $location]);

        $data = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'type' => ['nullable', Rule::enum(RoomType::class)],
            'capacity' => ['required', 'integer', 'min:1', 'max:500'],
            'isActive' => ['boolean'],
        ], [], ['name' => 'nombre', 'type' => 'tipo', 'capacity' => 'capacidad']);

        $dto = new RoomData($data['name'], RoomType::tryFrom((string) $data['type']), (int) $data['capacity'], (bool) $data['isActive']);

        $room ? $update->execute($room, $dto) : $create->execute($location, $dto);

        $this->showForm = false;
        $this->toast($room ? 'Sala actualizada.' : 'Sala creada.');
    }

    public function delete(int $roomId, DeleteRoom $delete): void
    {
        $room = $this->room($roomId);
        $this->authorize('delete', $room);

        $delete->execute($room);
        $this->toast('Sala eliminada.');
    }

    public function render(): View
    {
        $location = $this->location();

        return view('livewire.admin.locations.rooms', [
            'location' => $location,
            'rooms' => $location->rooms()->orderByDesc('is_active')->orderBy('name')->get(),
            'types' => RoomType::options(),
        ]);
    }

    private function location(): Location
    {
        return Location::query()->findOrFail($this->locationId);
    }

    /**
     * Solo salas de esta sede; LocationScope y la Policy validan el alcance.
     */
    private function room(int $roomId): Room
    {
        return Room::query()->where('location_id', $this->locationId)->findOrFail($roomId);
    }
}
