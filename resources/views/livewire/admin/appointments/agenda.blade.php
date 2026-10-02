<div>
    <x-page-header eyebrow="Operación" title="Agenda" :description="$title">
        <x-slot:actions>
            @if ($canBook)
                <x-button icon="plus" x-on:click="$dispatch('open-booking', { date: '{{ $date }}', locationId: {{ (int) $locationId }} })">Agendar cita</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <x-card :padding="false" class="mb-4">
        <div class="flex flex-wrap items-end gap-3 p-4 sm:px-6">
            <div class="flex items-center gap-1">
                <x-button variant="secondary" size="sm" wire:click="move(-1)" aria-label="Anterior"><x-icon name="arrow-left" class="size-4" /></x-button>
                <x-button variant="secondary" size="sm" wire:click="today">Hoy</x-button>
                <x-button variant="secondary" size="sm" wire:click="move(1)" aria-label="Siguiente"><x-icon name="chevron-right" class="size-4" /></x-button>
            </div>
            <div class="w-40"><x-input label="Fecha" type="date" wire:model.live="date" /></div>
            <div class="inline-flex rounded-lg ring-1 ring-steel-300" role="group" aria-label="Vista">
                <button type="button" wire:click="$set('view', 'day')" @class(['rounded-l-lg px-3 py-2 text-sm font-semibold', 'bg-ink-950 text-white' => $view === 'day', 'text-ink-950 hover:bg-steel-100' => $view !== 'day'])>Día</button>
                <button type="button" wire:click="$set('view', 'week')" @class(['rounded-r-lg px-3 py-2 text-sm font-semibold', 'bg-ink-950 text-white' => $view === 'week', 'text-ink-950 hover:bg-steel-100' => $view !== 'week'])>Semana</button>
            </div>
            @if ($this->locations->count() > 1)
                <div class="w-44"><x-select label="Sede" wire:model.live="locationId" :options="$this->locations->pluck('name', 'id')->all()" /></div>
            @endif
            @if ($staffOptions)
                <div class="w-48"><x-select label="Profesional" wire:model.live="staffId" :options="$staffOptions" :placeholder="$view === 'week' ? 'Elija uno' : 'Todos'" /></div>
            @endif
            <div class="w-48"><x-select label="Servicio" wire:model.live="serviceId" :options="$services" placeholder="Todos" /></div>
        </div>
    </x-card>

    @if (! $location)
        <x-card><x-empty-state icon="map-pin" title="Sin sedes" description="No tiene sedes asignadas." /></x-card>
    @elseif ($columns->isEmpty())
        <x-card><x-empty-state icon="calendar" title="Sin profesionales" description="No hay profesionales agendables en esta sede. Márquelos como “Atiende citas” en Staff y defina su disponibilidad." /></x-card>
    @else
        @php
            $minutes = ($endHour - $startHour) * 60;
            $height = (int) round($minutes * $scale);
            $offset = fn ($instant) => (int) round((((int) $instant->setTimezone($tz)->format('G') - $startHour) * 60 + (int) $instant->setTimezone($tz)->format('i')) * $scale);
        @endphp
        <div class="overflow-x-auto rounded-xl bg-surface ring-1 ring-steel-200">
            <div class="flex min-w-max">
                {{-- Horas --}}
                <div class="sticky left-0 z-10 w-14 shrink-0 border-r border-steel-200 bg-surface">
                    <div class="h-12 border-b border-steel-200"></div>
                    <div class="relative" style="height: {{ $height }}px">
                        @for ($h = $startHour; $h < $endHour; $h++)
                            <span class="absolute right-2 -translate-y-1/2 text-[11px] font-semibold text-steel-700" style="top: {{ (int) round(($h - $startHour) * 60 * $scale) }}px">{{ $h === 0 ? '12 am' : ($h < 12 ? $h.' am' : ($h === 12 ? '12 m' : ($h - 12).' pm')) }}</span>
                        @endfor
                    </div>
                </div>

                @foreach ($columns as $column)
                    <div wire:key="col-{{ $column['key'] }}" class="w-48 shrink-0 border-r border-steel-200 last:border-r-0 sm:w-56">
                        <div class="flex h-12 items-center justify-center border-b border-steel-200 px-2 text-center">
                            <p class="truncate text-sm font-bold text-ink-950">{{ $column['label'] }}</p>
                        </div>
                        <div class="relative bg-steel-100/70" style="height: {{ $height }}px">
                            @for ($h = $startHour; $h < $endHour; $h++)
                                <div class="pointer-events-none absolute inset-x-0 border-t border-steel-200" style="top: {{ (int) round(($h - $startHour) * 60 * $scale) }}px"></div>
                            @endfor

                            {{-- Disponibilidad: franjas en blanco, con huecos de 30 minutos para agendar --}}
                            @foreach ($column['windows'] as [$from, $until])
                                @php $top = $offset($from); $bottom = $offset($until); @endphp
                                <div class="absolute inset-x-0 bg-surface" style="top: {{ $top }}px; height: {{ max(0, $bottom - $top) }}px">
                                    @if ($canBook)
                                        @for ($t = $from; $t->lessThan($until); $t = $t->addMinutes(30))
                                            @php
                                                $tEnd = $t->addMinutes(30);
                                                $taken = $column['appointments']->contains(fn ($ap) => $ap->isActive() && $t->lessThan($ap->blockEndsAt()) && $tEnd->greaterThan($ap->starts_at));
                                            @endphp
                                            @if ($t->greaterThan(now()) && ! $taken)
                                                <button type="button" class="group absolute inset-x-0 text-left" style="top: {{ $offset($t) - $top }}px; height: {{ (int) round(30 * $scale) }}px"
                                                    x-on:click="$dispatch('open-booking', { staffId: {{ $column['staff']->id }}, date: @js($column['date']), time: @js($t->setTimezone($tz)->format('H:i')), locationId: {{ $location->id }} })"
                                                    aria-label="Agendar con {{ $column['staff']->full_name }} a las {{ $t->setTimezone($tz)->format('g:i a') }}">
                                                    <span class="hidden px-2 text-[11px] font-semibold text-brand-700 group-hover:block">+ {{ $t->setTimezone($tz)->format('g:i a') }}</span>
                                                </button>
                                            @endif
                                        @endfor
                                    @endif
                                </div>
                            @endforeach

                            {{-- Citas --}}
                            @foreach ($column['appointments'] as $a)
                                @php
                                    $top = $offset($a->starts_at);
                                    $h = max(28, $offset($a->ends_at) - $top);
                                    $muted = in_array($a->status, [\App\Domain\Appointments\Enums\AppointmentStatus::Cancelled, \App\Domain\Appointments\Enums\AppointmentStatus::NoShow], true);
                                @endphp
                                <button type="button" wire:key="ap-{{ $a->id }}" x-on:click="$dispatch('open-appointment', { id: {{ $a->id }} })"
                                    @class(['absolute inset-x-1 overflow-hidden rounded-md border-l-4 bg-surface px-2 py-1 text-left shadow-sm ring-1 ring-steel-200 hover:ring-brand-500', 'opacity-60' => $muted])
                                    style="top: {{ $top }}px; height: {{ $h }}px; border-left-color: {{ $a->service?->color ?: '#828282' }}">
                                    <p class="truncate text-[11px] font-bold text-steel-700">{{ $a->starts_at->setTimezone($tz)->format('g:i') }} · {{ $a->status->label() }}</p>
                                    <p @class(['truncate text-sm font-semibold text-ink-950', 'line-through' => $muted])>{{ $a->member?->full_name }}</p>
                                    <p class="truncate text-xs text-steel-700">{{ $a->service?->name }}</p>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        <p class="mt-2 text-xs text-steel-700">Las franjas en blanco son la disponibilidad de cada profesional. Toque un espacio libre para agendar o una cita para ver sus acciones.</p>
    @endif

    <livewire:admin.appointments.booking-modal />
    <livewire:admin.appointments.appointment-panel />
</div>
