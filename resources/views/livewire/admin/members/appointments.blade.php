<div class="space-y-6">
    <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
        <x-card title="Próximas citas" :padding="false">
            @if ($canBook)
                <x-slot:actions><x-button size="sm" icon="plus" x-on:click="$dispatch('open-booking', { memberId: {{ $member->id }} })">Agendar cita</x-button></x-slot:actions>
            @endif
            @if ($upcoming->isEmpty())
                <p class="px-6 py-5 text-sm text-steel-700">No tiene citas próximas.</p>
            @else
                <ul class="divide-y divide-steel-200">
                    @foreach ($upcoming as $a)
                        @php $tz = $a->location?->timezone ?: 'UTC'; @endphp
                        <li wire:key="up-{{ $a->id }}">
                            <button type="button" x-on:click="$dispatch('open-appointment', { id: {{ $a->id }} })" class="flex w-full items-center gap-4 px-6 py-3 text-left hover:bg-canvas">
                                <span class="h-10 w-1 shrink-0 rounded-full" style="background: {{ $a->service?->color ?: '#828282' }}" aria-hidden="true"></span>
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold text-ink-950">{{ $a->service?->name }} · {{ $a->staff?->full_name }}</p>
                                    <p class="text-sm text-steel-700">{{ ucfirst($a->starts_at->setTimezone($tz)->locale('es')->translatedFormat('l j \d\e F, g:i a')) }} · {{ $a->location?->name }}</p>
                                </div>
                                <x-badge :color="$a->status->color()">{{ $a->status->label() }}</x-badge>
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        <x-card title="Sesiones disponibles" description="Saldo de las membresías vigentes.">
            @if ($canAdjust)
                <x-slot:actions><x-button size="sm" variant="secondary" wire:click="openAdjust">Ajustar</x-button></x-slot:actions>
            @endif
            @forelse ($balances as $b)
                <div class="flex items-center justify-between border-b border-steel-200 py-2 last:border-0">
                    <div>
                        <p class="text-sm font-semibold text-ink-950">{{ $b->service }}</p>
                        <p class="text-xs text-steel-700">{{ $b->plan }}</p>
                    </div>
                    <p class="font-display text-3xl font-bold text-ink-950">{{ $b->balance }}</p>
                </div>
            @empty
                <p class="text-sm text-steel-700">Sin sesiones incluidas. Las citas se cobran como sesión suelta, salvo que el plan las incluya sin límite.</p>
            @endforelse

            @if ($recentCredits->isNotEmpty())
                <details class="mt-4 text-sm">
                    <summary class="cursor-pointer font-semibold text-steel-700">Movimientos recientes</summary>
                    <ul class="mt-2 space-y-1 text-steel-700">
                        @foreach ($recentCredits as $c)
                            <li><x-datetime :value="$c->created_at" format="d/m/Y" /> · {{ $c->service?->name }} · <span @class(['font-semibold', 'text-success-700' => $c->delta > 0, 'text-danger-700' => $c->delta < 0])>{{ $c->delta > 0 ? '+' : '' }}{{ $c->delta }}</span> · {{ $c->reason->label() }}</li>
                        @endforeach
                    </ul>
                </details>
            @endif
        </x-card>
    </div>

    <x-card title="Historial de citas" :padding="false">
        @if ($past->isEmpty())
            <p class="px-6 py-5 text-sm text-steel-700">Sin citas anteriores.</p>
        @else
            <x-table>
                <x-slot:head>
                    <th scope="col" class="px-6 py-3">Fecha</th>
                    <th scope="col" class="px-4 py-3">Servicio</th>
                    <th scope="col" class="px-4 py-3">Profesional</th>
                    <th scope="col" class="px-6 py-3">Estado</th>
                </x-slot:head>
                @foreach ($past as $a)
                    <tr wire:key="past-{{ $a->id }}" class="cursor-pointer hover:bg-canvas" x-on:click="$dispatch('open-appointment', { id: {{ $a->id }} })">
                        <td class="whitespace-nowrap px-6 py-3 text-ink-950"><x-datetime :value="$a->starts_at" /></td>
                        <td class="px-4 py-3 text-steel-700">{{ $a->service?->name }}</td>
                        <td class="px-4 py-3 text-steel-700">{{ $a->staff?->full_name }}</td>
                        <td class="px-6 py-3"><x-badge :color="$a->status->color()" dot>{{ $a->status->label() }}</x-badge></td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>

    <x-modal wire:model="showAdjust" title="Ajustar sesiones" max-width="md">
        <form wire:submit="adjust" id="adjust-form" class="space-y-4">
            @if ($memberships)
                <x-select label="Membresía" wire:model="adjustMembershipId" :options="$memberships" placeholder="Seleccione" required />
                <x-select label="Servicio" wire:model="adjustServiceId" :options="$serviceOptions" placeholder="Seleccione" required />
                <x-input label="Cantidad" type="number" min="-100" max="100" wire:model="adjustDelta" hint="Positivo para sumar (cortesía), negativo para corregir." required />
                <x-input label="Motivo" wire:model="adjustReason" maxlength="200" required />
            @else
                <p class="text-sm text-steel-700">El cliente no tiene membresías vigentes.</p>
            @endif
        </form>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="open = false">Cancelar</x-button>
            <x-button type="submit" form="adjust-form">Guardar ajuste</x-button>
        </x-slot:footer>
    </x-modal>

    <livewire:admin.appointments.booking-modal />
    <livewire:admin.appointments.appointment-panel />
</div>
