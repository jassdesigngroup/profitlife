<div>
    <x-page-header eyebrow="Equipo" title="Staff" description="Personas con acceso al panel, sus roles y sedes.">
        @can('create', \App\Domain\Staff\Models\Staff::class)
            <x-slot:actions>
                <x-button :href="route('admin.staff.create')" icon="plus" wire:navigate>Nuevo staff</x-button>
            </x-slot:actions>
        @endcan
    </x-page-header>

    <x-card :padding="false">
        <div class="grid gap-3 border-b border-steel-200 p-4 sm:grid-cols-2 sm:px-6 lg:grid-cols-[2fr_1fr_1fr_1fr]">
            <div class="relative sm:col-span-2 lg:col-span-1">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-steel-500" />
                <label for="staff-search" class="sr-only">Buscar</label>
                <input id="staff-search" type="search" wire:model.live.debounce.300ms="search" placeholder="Nombre, correo, documento o cargo"
                    class="block w-full rounded-lg border-0 bg-surface py-2.5 pl-9 pr-3 text-sm ring-1 ring-inset ring-steel-300 placeholder:text-steel-500 focus:ring-2 focus:ring-brand-500">
            </div>
            <x-select wire:model.live="role" :options="$roles" placeholder="Todos los roles" aria-label="Rol" />
            <x-select wire:model.live="location" :options="$locations" placeholder="Todas mis sedes" aria-label="Sede" />
            <x-select wire:model.live="status" :options="$statuses" placeholder="Todos los estados" aria-label="Estado" />
        </div>

        @if ($staff->isEmpty())
            <x-empty-state icon="users" title="No hay resultados" description="Ajuste los filtros o invite a una persona nueva." />
        @else
            <x-table>
                <x-slot:head>
                    <th scope="col" class="px-6 py-3">Persona</th>
                    <th scope="col" class="px-4 py-3">Roles</th>
                    <th scope="col" class="px-4 py-3">Sedes</th>
                    <th scope="col" class="px-4 py-3">Estado</th>
                    <th scope="col" class="px-6 py-3"><span class="sr-only">Acciones</span></th>
                </x-slot:head>
                @foreach ($staff as $member)
                    @php $pending = ! $member->user->hasAcceptedInvitation(); @endphp
                    <tr wire:key="staff-{{ $member->id }}" class="hover:bg-canvas">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-ink-950 text-xs font-bold text-white ring-2 ring-offset-2" style="--tw-ring-color: {{ $member->calendar_color ?? '#828282' }}">{{ $member->user->initials() }}</span>
                                <div class="min-w-0">
                                    <p class="truncate font-semibold text-ink-950">{{ $member->full_name }}</p>
                                    <p class="truncate text-xs text-steel-700">{{ $member->user->email }}@if ($member->job_title) · {{ $member->job_title }}@endif</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-4">
                            <div class="flex flex-wrap gap-1">
                                @forelse ($member->user->roleEnums() as $role)
                                    <x-badge :color="$role === \App\Domain\Identity\Enums\RoleName::SuperAdmin ? 'dark' : 'brand'">{{ $role->label() }}</x-badge>
                                @empty
                                    <span class="text-xs text-steel-700">Sin rol</span>
                                @endforelse
                            </div>
                        </td>
                        <td class="px-4 py-4 text-steel-700">
                            {{ $member->locations->sortByDesc('pivot.is_primary')->pluck('name')->implode(', ') ?: '—' }}
                        </td>
                        <td class="px-4 py-4">
                            @if ($pending)
                                <x-badge color="warning" dot>Invitación pendiente</x-badge>
                            @else
                                <x-badge :color="$member->status->color()" dot>{{ $member->status->label() }}</x-badge>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex justify-end gap-1">
                                @can('update', $member)
                                    @if ($member->is_bookable)
                                        <x-button variant="ghost" size="sm" :href="route('admin.staff.availability', $member)" wire:navigate>Disponibilidad</x-button>
                                    @endif
                                    <x-button variant="ghost" size="sm" :href="route('admin.staff.edit', $member)" wire:navigate>Editar</x-button>
                                @endcan
                                @if ($pending)
                                    @can('resendInvitation', $member)
                                        <x-button variant="ghost" size="sm" wire:click="resendInvitation({{ $member->id }})">Reenviar invitación</x-button>
                                    @endcan
                                @endif
                                @can('toggleStatus', $member)
                                    <x-button variant="ghost" size="sm" wire:click="toggleStatus({{ $member->id }})"
                                        wire:confirm="{{ $member->isActive() ? '¿Desactivar a '.$member->full_name.'? Perderá el acceso de inmediato.' : '¿Activar a '.$member->full_name.'?' }}">
                                        {{ $member->isActive() ? 'Desactivar' : 'Activar' }}
                                    </x-button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-table>
            @if ($staff->hasPages())
                <div class="border-t border-steel-200 px-6 py-3">{{ $staff->links() }}</div>
            @endif
        @endif
    </x-card>
</div>
