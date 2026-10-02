<div>
    <x-page-header eyebrow="Operación" title="Asistencia" description="Ingresos por kiosco y por recepción, con los rechazos y su motivo." />

    @if ($canRegister)
        <x-card title="Registrar ingreso" class="mb-6">
            <div class="grid gap-3 sm:grid-cols-[1fr_16rem]">
                <div class="relative">
                    <x-input label="Cliente" wire:model.live.debounce.300ms="search" placeholder="Nombre, número de cliente, documento o celular" autocomplete="off" />
                    @if ($matches->isNotEmpty())
                        <ul class="absolute inset-x-0 top-full z-20 mt-1 divide-y divide-steel-200 overflow-hidden rounded-lg bg-surface shadow-lg ring-1 ring-steel-200">
                            @foreach ($matches as $match)
                                <li wire:key="match-{{ $match->id }}" class="flex items-center justify-between gap-3 px-4 py-2.5">
                                    <div class="min-w-0">
                                        <p class="truncate font-semibold text-ink-950">{{ $match->full_name }}</p>
                                        <p class="font-mono text-xs text-steel-700">{{ $match->member_number }}</p>
                                    </div>
                                    <x-button size="sm" icon="enter" wire:click="checkIn({{ $match->id }})" wire:loading.attr="disabled">Registrar</x-button>
                                </li>
                            @endforeach
                        </ul>
                    @elseif (mb_strlen(trim($search)) >= 2)
                        <p class="mt-2 text-sm text-steel-700">Sin coincidencias.</p>
                    @endif
                </div>
                @if ($this->deskLocations->count() > 1)
                    <x-select label="Sede" wire:model="deskLocationId" :options="$this->deskLocations->pluck('name', 'id')->all()" />
                @endif
            </div>
            <x-field-error name="deskLocationId" />
            @if ($outcome)
                <div class="mt-4">@include('livewire.admin.check-ins.partials.outcome')</div>
            @endif
        </x-card>
    @endif

    <x-card :padding="false" class="mb-6">
        <div class="grid gap-3 p-4 sm:grid-cols-3 sm:px-6">
            <x-input label="Fecha" type="date" wire:model.live="date" />
            <x-select label="Resultado" wire:model.live="result" :options="$results" placeholder="Todos" />
            <x-select label="Medio" wire:model.live="method" :options="$methods" placeholder="Todos" />
        </div>
    </x-card>

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl bg-ink-950 p-5 text-white">
            <p class="text-xs font-bold uppercase tracking-wider text-brand-500">Ingresos aceptados</p>
            <p class="mt-1 font-display text-4xl font-bold">{{ $accepted }}</p>
        </div>
        <div class="rounded-xl bg-surface p-5 ring-1 ring-steel-200">
            <p class="text-xs font-bold uppercase tracking-wider text-steel-700">Clientes distintos</p>
            <p class="mt-1 font-display text-3xl font-bold text-ink-950">{{ $uniqueMembers }}</p>
        </div>
        <div class="rounded-xl bg-surface p-5 ring-1 ring-steel-200">
            <p class="text-xs font-bold uppercase tracking-wider text-steel-700">Rechazados</p>
            <p class="mt-1 font-display text-3xl font-bold text-danger-700">{{ $rejected }}</p>
        </div>
    </div>

    <x-card :padding="false">
        @if ($checkIns->isEmpty())
            <x-empty-state icon="enter" title="Sin ingresos en la fecha" />
        @else
            <x-table>
                <x-slot:head>
                    <th scope="col" class="px-6 py-3">Hora</th>
                    <th scope="col" class="px-4 py-3">Cliente</th>
                    <th scope="col" class="px-4 py-3">Resultado</th>
                    <th scope="col" class="px-4 py-3">Medio</th>
                    <th scope="col" class="px-4 py-3">Origen</th>
                    <th scope="col" class="px-6 py-3"><span class="sr-only">Acciones</span></th>
                </x-slot:head>
                @foreach ($checkIns as $checkIn)
                    <tr wire:key="ci-{{ $checkIn->id }}">
                        <td class="whitespace-nowrap px-6 py-3 font-semibold text-ink-950"><x-datetime :value="$checkIn->checked_in_at" format="H:i" /></td>
                        <td class="px-4 py-3">
                            @if ($checkIn->member)
                                <a href="{{ route('admin.members.show', ['member' => $checkIn->member_id, 'tab' => 'checkins']) }}" wire:navigate class="font-semibold text-ink-950 hover:text-brand-700">{{ $checkIn->member->full_name }}</a>
                                <p class="font-mono text-xs text-steel-700">{{ $checkIn->member->member_number }}@if ($checkIn->membership) · {{ $checkIn->membership->plan?->name }}@endif</p>
                            @else
                                <span class="text-steel-700">No identificado</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <x-badge :color="$checkIn->result->color()" dot>{{ $checkIn->result->label() }}</x-badge>
                            @if ($checkIn->rejection_reason)
                                <p class="mt-1 text-xs text-steel-700">{{ $checkIn->rejection_reason->label() }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-steel-700">{{ $checkIn->method->label() }}</td>
                        <td class="px-4 py-3 text-steel-700">
                            {{ $checkIn->kioskDevice?->name ?? $checkIn->registeredBy?->name ?? '—' }}
                            <p class="text-xs">{{ $checkIn->location?->name }}</p>
                        </td>
                        <td class="px-6 py-3 text-right">
                            @can('override', $checkIn)
                                @if ($checkIn->checked_in_at->setTimezone(app(\App\Domain\Settings\Services\Settings::class)->displayTimezone())->isToday())
                                    <x-button variant="ghost" size="sm" wire:click="openOverride({{ $checkIn->id }})">Autorizar</x-button>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </x-table>
            @if ($checkIns->hasPages())
                <div class="border-t border-steel-200 px-6 py-3">{{ $checkIns->links() }}</div>
            @endif
        @endif
    </x-card>

    @include('livewire.admin.check-ins.partials.override-modal')
</div>
