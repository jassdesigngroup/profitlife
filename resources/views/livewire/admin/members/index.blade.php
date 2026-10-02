<div>
    <x-page-header eyebrow="Operación" title="Clientes" description="Personas registradas en sus sedes.">
        @can('create', \App\Domain\Members\Models\Member::class)
            <x-slot:actions>
                <x-button :href="route('admin.members.create')" icon="plus" wire:navigate>Nuevo cliente</x-button>
            </x-slot:actions>
        @endcan
    </x-page-header>

    <x-card :padding="false">
        <div class="grid gap-3 border-b border-steel-200 p-4 sm:grid-cols-2 sm:px-6 lg:grid-cols-[2fr_1fr_1fr]">
            <div class="relative sm:col-span-2 lg:col-span-1">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-steel-500" />
                <label for="members-search" class="sr-only">Buscar</label>
                <input id="members-search" type="search" wire:model.live.debounce.300ms="search" placeholder="Nombre, documento, teléfono, correo o número de cliente"
                    class="block w-full rounded-lg border-0 bg-surface py-2.5 pl-9 pr-3 text-sm ring-1 ring-inset ring-steel-300 placeholder:text-steel-500 focus:ring-2 focus:ring-brand-500">
            </div>
            <x-select wire:model.live="location" :options="$locations" placeholder="Todas mis sedes" aria-label="Sede" />
            <x-select wire:model.live="status" :options="$statuses" placeholder="Todos los estados" aria-label="Estado" />
        </div>

        @if ($members->isEmpty())
            <x-empty-state icon="users" title="No hay clientes" description="Ajuste la búsqueda o registre un cliente nuevo." />
        @else
            <x-table>
                <x-slot:head>
                    <th scope="col" class="px-6 py-3">Cliente</th>
                    <th scope="col" class="px-4 py-3">Documento</th>
                    <th scope="col" class="px-4 py-3">Teléfono</th>
                    <th scope="col" class="px-4 py-3">Sede</th>
                    <th scope="col" class="px-4 py-3">Estado</th>
                    <th scope="col" class="px-6 py-3"><span class="sr-only">Acciones</span></th>
                </x-slot:head>
                @foreach ($members as $member)
                    <tr wire:key="member-{{ $member->id }}" class="hover:bg-canvas">
                        <td class="px-6 py-4">
                            <a href="{{ route('admin.members.show', $member) }}" wire:navigate class="flex items-center gap-3">
                                <span class="flex size-9 shrink-0 items-center justify-center overflow-hidden rounded-full bg-ink-950 text-xs font-bold text-white">
                                    @if ($member->photo_path)
                                        <img src="{{ route('admin.members.photo', $member) }}" alt="" class="size-full object-cover" loading="lazy">
                                    @else
                                        {{ $member->initials() }}
                                    @endif
                                </span>
                                <span class="min-w-0">
                                    <span class="block truncate font-semibold text-ink-950 hover:text-brand-700">{{ $member->full_name }}</span>
                                    <span class="block font-mono text-xs text-steel-700">{{ $member->member_number }}</span>
                                </span>
                            </a>
                        </td>
                        <td class="px-4 py-4 text-steel-700">{{ $member->document_type?->value }} {{ $member->document_number ?? '—' }}</td>
                        <td class="px-4 py-4 text-steel-700">{{ $member->phone ?? '—' }}</td>
                        <td class="px-4 py-4 text-steel-700">{{ $member->homeLocation?->name }}</td>
                        <td class="px-4 py-4"><x-badge :color="$member->status->color()" dot>{{ $member->status->label() }}</x-badge></td>
                        <td class="px-6 py-4 text-right">
                            <x-button variant="ghost" size="sm" :href="route('admin.members.show', $member)" wire:navigate>Ver</x-button>
                        </td>
                    </tr>
                @endforeach
            </x-table>
            @if ($members->hasPages())
                <div class="border-t border-steel-200 px-6 py-3">{{ $members->links() }}</div>
            @endif
        @endif
    </x-card>
</div>
