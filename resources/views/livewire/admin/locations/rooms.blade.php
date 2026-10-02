<div>
    <x-card title="Salas" description="Consultorios, salas y zonas de la sede." :padding="false">
        @can('create', [\App\Domain\Locations\Models\Room::class, $location])
            <x-slot:actions>
                <x-button size="sm" icon="plus" wire:click="create">Nueva sala</x-button>
            </x-slot:actions>
        @endcan

        @if ($rooms->isEmpty())
            <x-empty-state icon="squares" title="Sin salas" description="Cree las salas para asignarlas a citas y clases." />
        @else
            <x-table>
                <x-slot:head>
                    <th scope="col" class="px-6 py-3">Sala</th>
                    <th scope="col" class="px-4 py-3">Tipo</th>
                    <th scope="col" class="px-4 py-3 text-center">Capacidad</th>
                    <th scope="col" class="px-4 py-3">Estado</th>
                    <th scope="col" class="px-6 py-3"><span class="sr-only">Acciones</span></th>
                </x-slot:head>
                @foreach ($rooms as $room)
                    <tr wire:key="room-{{ $room->id }}" class="hover:bg-canvas">
                        <td class="px-6 py-4 font-semibold text-ink-950">{{ $room->name }}</td>
                        <td class="px-4 py-4 text-steel-700">{{ $room->type?->label() ?? '—' }}</td>
                        <td class="px-4 py-4 text-center font-semibold">{{ $room->capacity }}</td>
                        <td class="px-4 py-4"><x-badge :color="$room->is_active ? 'success' : 'neutral'" dot>{{ $room->is_active ? 'Activa' : 'Inactiva' }}</x-badge></td>
                        <td class="px-6 py-4">
                            <div class="flex justify-end gap-1">
                                @can('update', $room)
                                    <x-button variant="ghost" size="sm" wire:click="edit({{ $room->id }})">Editar</x-button>
                                @endcan
                                @can('delete', $room)
                                    <x-button variant="ghost" size="sm" class="text-danger-700" wire:click="delete({{ $room->id }})" wire:confirm="¿Eliminar la sala {{ $room->name }}?">Eliminar</x-button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>

    <x-modal wire:model="showForm" :title="$roomId ? 'Editar sala' : 'Nueva sala'" max-width="md">
        <form wire:submit="save" id="room-form" class="space-y-4">
            <x-input label="Nombre" wire:model="name" maxlength="100" required />
            <div class="grid grid-cols-2 gap-4">
                <x-select label="Tipo" wire:model="type" :options="$types" placeholder="Sin tipo" />
                <x-input label="Capacidad" type="number" min="1" max="500" wire:model="capacity" required />
            </div>
            <x-checkbox wire:model="isActive" label="Sala activa" description="Las salas inactivas no se ofrecen al agendar." />
        </form>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="open = false">Cancelar</x-button>
            <x-button type="submit" form="room-form" wire:loading.attr="disabled">Guardar</x-button>
        </x-slot:footer>
    </x-modal>
</div>
