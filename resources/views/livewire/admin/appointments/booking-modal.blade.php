<div>
    <x-modal wire:model="show" title="Agendar cita" max-width="2xl">
        <form wire:submit="book" id="booking-form" class="space-y-5">
            {{-- Cliente --}}
            <div>
                @if ($member)
                    <div class="flex items-center justify-between rounded-lg bg-canvas px-4 py-3 ring-1 ring-steel-200">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider text-steel-700">Cliente</p>
                            <p class="font-semibold text-ink-950">{{ $member->full_name }} <span class="font-mono text-xs text-steel-700">{{ $member->member_number }}</span></p>
                        </div>
                        @unless ($memberFixed)
                            <x-button variant="ghost" size="sm" wire:click="$set('memberId', '')">Cambiar</x-button>
                        @endunless
                    </div>
                @else
                    <div class="relative">
                        <x-input label="Cliente" wire:model.live.debounce.300ms="memberSearch" placeholder="Nombre, número, documento o celular" autocomplete="off" />
                        @if ($matches->isNotEmpty())
                            <ul class="absolute inset-x-0 top-full z-20 mt-1 divide-y divide-steel-200 overflow-hidden rounded-lg bg-surface shadow-lg ring-1 ring-steel-200">
                                @foreach ($matches as $match)
                                    <li><button type="button" wire:click="pickMember({{ $match->id }})" class="flex w-full items-center justify-between px-4 py-2.5 text-left hover:bg-canvas">
                                        <span class="font-semibold text-ink-950">{{ $match->full_name }}</span>
                                        <span class="font-mono text-xs text-steel-700">{{ $match->member_number }}</span>
                                    </button></li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                    <x-field-error name="memberId" />
                @endif
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                @if ($this->locations->count() > 1)
                    <x-select label="Sede" wire:model.live="locationId" :options="$this->locations->pluck('name', 'id')->all()" />
                @endif
                <x-select label="Servicio" wire:model.live="serviceId" placeholder="Seleccione" required>
                    @foreach ($services as $s)
                        <option value="{{ $s->id }}">{{ $s->name }} · {{ $s->duration_minutes }} min</option>
                    @endforeach
                </x-select>
                <x-input label="Fecha" type="date" wire:model.live="date" required />
                @if ($service && $staffOptions->count() > 1)
                    <x-select label="Profesional" wire:model.live="staffId" :options="$staffOptions->pluck('full_name', 'id')->all()" placeholder="Cualquiera" />
                @endif
            </div>

            {{-- Horarios --}}
            @if ($service)
                <div>
                    <p class="mb-2 text-sm font-semibold text-ink-950">Horario</p>
                    @if ($freeSlots->isEmpty())
                        <p class="rounded-lg bg-canvas px-4 py-3 text-sm text-steel-700 ring-1 ring-steel-200">
                            {{ $staffOptions->isEmpty() ? 'Ningún profesional presta este servicio en la sede.' : 'No hay horarios libres ese día.' }}
                        </p>
                    @else
                        <div class="grid max-h-56 grid-cols-3 gap-2 overflow-y-auto sm:grid-cols-5">
                            @foreach ($freeSlots as $s)
                                @php $value = $s['staff_id'].'|'.$s['starts_at']->toIso8601ZuluString(); @endphp
                                <label wire:key="slot-{{ $value }}" @class([
                                    'cursor-pointer rounded-lg px-2 py-2 text-center text-sm ring-1 transition',
                                    'bg-brand-500 font-bold text-white ring-brand-500' => $pickedSlot === $value,
                                    'bg-surface text-ink-950 ring-steel-300 hover:bg-steel-100' => $pickedSlot !== $value,
                                ])>
                                    <input type="radio" wire:model.live="pickedSlot" value="{{ $value }}" class="sr-only">
                                    {{ $s['starts_at']->setTimezone($tz)->format('g:i a') }}
                                    @if ($staffId === '' && $staffOptions->count() > 1)
                                        <span class="block truncate text-[11px] opacity-80">{{ \Illuminate\Support\Str::before($s['staff_name'], ' ') }}</span>
                                    @endif
                                </label>
                            @endforeach
                        </div>
                    @endif
                    <x-field-error name="pickedSlot" />
                    <x-field-error name="startsAt" />
                    <x-field-error name="roomId" />
                    <x-field-error name="serviceId" />
                    <x-field-error name="member" />
                </div>
            @endif

            @if ($coverage)
                <div @class(['flex items-start gap-2 rounded-lg px-4 py-3 text-sm ring-1', 'bg-warning-50 text-warning-700 ring-warning-700/20' => $isInvoice, 'bg-success-50 text-success-700 ring-success-700/20' => ! $isInvoice])>
                    <x-icon :name="$isInvoice ? 'info' : 'check-circle'" class="mt-0.5 size-4 shrink-0" />
                    <span>{{ $coverage->describe() }}</span>
                </div>
            @endif

            <x-textarea label="Notas administrativas (opcional)" wire:model="notes" rows="2" hint="No escriba información clínica aquí." />
        </form>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="open = false">Cancelar</x-button>
            <x-button type="submit" form="booking-form" icon="check" wire:loading.attr="disabled">Agendar</x-button>
        </x-slot:footer>
    </x-modal>
</div>
