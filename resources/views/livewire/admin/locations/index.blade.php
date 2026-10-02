<div>
    <x-page-header eyebrow="Estructura" title="Sedes" description="Centros, horarios, cierres y salas.">
        @can('create', \App\Domain\Locations\Models\Location::class)
            <x-slot:actions>
                <x-button :href="route('admin.locations.create')" icon="plus" wire:navigate>Nueva sede</x-button>
            </x-slot:actions>
        @endcan
    </x-page-header>

    <x-card :padding="false">
        <div class="flex flex-col gap-3 border-b border-steel-200 p-4 sm:flex-row sm:items-center sm:px-6">
            <div class="relative flex-1">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-steel-500" />
                <label for="locations-search" class="sr-only">Buscar</label>
                <input id="locations-search" type="search" wire:model.live.debounce.300ms="search" placeholder="Buscar por nombre, código o ciudad"
                    class="block w-full rounded-lg border-0 bg-surface py-2.5 pl-9 pr-3 text-sm ring-1 ring-inset ring-steel-300 placeholder:text-steel-500 focus:ring-2 focus:ring-brand-500">
            </div>
            <x-select wire:model.live="status" :options="['active' => 'Activas', 'inactive' => 'Inactivas']" placeholder="Todos los estados" class="sm:w-48" aria-label="Estado" />
        </div>

        @if ($locations->isEmpty())
            <x-empty-state icon="map-pin" title="No hay sedes" description="No encontramos sedes con esos filtros." />
        @else
            <x-table>
                <x-slot:head>
                    <th scope="col" class="px-6 py-3">Sede</th>
                    <th scope="col" class="px-4 py-3">Ciudad</th>
                    <th scope="col" class="px-4 py-3 text-center">Salas</th>
                    <th scope="col" class="px-4 py-3 text-center">Staff</th>
                    <th scope="col" class="px-4 py-3">Estado</th>
                    <th scope="col" class="px-6 py-3"><span class="sr-only">Acciones</span></th>
                </x-slot:head>
                @foreach ($locations as $location)
                    <tr wire:key="location-{{ $location->id }}" class="hover:bg-canvas">
                        <td class="px-6 py-4">
                            <a href="{{ route('admin.locations.show', $location) }}" wire:navigate class="font-semibold text-ink-950 hover:text-brand-700">{{ $location->name }}</a>
                            <p class="text-xs font-semibold uppercase tracking-wider text-steel-700">{{ $location->code }}</p>
                        </td>
                        <td class="px-4 py-4 text-steel-700">{{ $location->city }}, {{ $location->department }}</td>
                        <td class="px-4 py-4 text-center font-semibold">{{ $location->rooms_count }}</td>
                        <td class="px-4 py-4 text-center font-semibold">{{ $location->staff_count }}</td>
                        <td class="px-4 py-4">
                            <x-badge :color="$location->is_active ? 'success' : 'neutral'" dot>{{ $location->is_active ? 'Activa' : 'Inactiva' }}</x-badge>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex justify-end gap-1">
                                <x-button variant="ghost" size="sm" :href="route('admin.locations.show', $location)" wire:navigate>Ver</x-button>
                                @can('update', $location)
                                    <x-button variant="ghost" size="sm" :href="route('admin.locations.edit', $location)" wire:navigate>Editar</x-button>
                                @endcan
                                @can('toggleStatus', $location)
                                    <x-button variant="ghost" size="sm" wire:click="toggleStatus({{ $location->id }})"
                                        wire:confirm="{{ $location->is_active ? '¿Desactivar '.$location->name.'?' : '¿Activar '.$location->name.'?' }}">
                                        {{ $location->is_active ? 'Desactivar' : 'Activar' }}
                                    </x-button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-table>
            @if ($locations->hasPages())
                <div class="border-t border-steel-200 px-6 py-3">{{ $locations->links() }}</div>
            @endif
        @endif
    </x-card>
</div>
