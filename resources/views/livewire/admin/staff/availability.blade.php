<div>
    <a href="{{ route('admin.staff.index') }}" wire:navigate class="mb-4 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-700 hover:underline">
        <x-icon name="arrow-left" class="size-4" /> Staff
    </a>
    <x-page-header eyebrow="Disponibilidad" :title="$staff->full_name" :description="$staff->is_bookable ? 'Franjas semanales en las que se le pueden agendar citas.' : 'Este profesional no está marcado como agendable: márquelo en su perfil para que aparezca al agendar.'" />

    <div class="grid gap-6 lg:grid-cols-[1fr_24rem]">
        <x-card title="Horario semanal" description="Hora local de la sede. Se pueden tener varias franjas por día (jornada partida).">
            <form wire:submit="saveSchedules" class="space-y-3">
                @forelse ($schedules as $i => $row)
                    <div wire:key="sch-{{ $i }}" class="grid grid-cols-2 items-end gap-2 rounded-lg bg-canvas p-3 ring-1 ring-steel-200 sm:grid-cols-[1fr_1fr_8.5rem_8.5rem_auto]">
                        <x-select label="Día" wire:model="schedules.{{ $i }}.day_of_week" :options="$days" />
                        <x-select label="Sede" wire:model="schedules.{{ $i }}.location_id" :options="$locations" />
                        <x-input label="Desde" type="time" step="900" wire:model="schedules.{{ $i }}.starts_at" />
                        <x-input label="Hasta" type="time" step="900" wire:model="schedules.{{ $i }}.ends_at" />
                        <x-button variant="ghost" size="sm" class="text-danger-700" wire:click="removeRow({{ $i }})" aria-label="Quitar franja"><x-icon name="trash" class="size-4" /></x-button>
                    </div>
                @empty
                    <p class="text-sm text-steel-700">Sin franjas. Agregue al menos una para poder agendarle citas.</p>
                @endforelse
                <x-field-error name="schedules" />
                <div class="flex flex-wrap gap-2 pt-2">
                    <x-button variant="secondary" size="sm" icon="plus" wire:click="addRow">Agregar franja</x-button>
                    <x-button variant="ghost" size="sm" wire:click="copyMonday">Copiar el lunes a martes–viernes</x-button>
                    <x-button type="submit" size="sm" icon="check" class="ml-auto">Guardar horario</x-button>
                </div>
            </form>

            @if ($readOnly->isNotEmpty())
                <div class="mt-5 rounded-lg bg-canvas p-3 text-sm text-steel-700 ring-1 ring-steel-200">
                    <p class="font-semibold text-ink-950">En otras sedes (no las puede editar):</p>
                    <ul class="mt-1">
                        @foreach ($readOnly as $s)
                            <li>{{ $s->day_of_week->label() }} {{ substr($s->starts_at, 0, 5) }}–{{ substr($s->ends_at, 0, 5) }} · {{ $s->location?->name }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </x-card>

        <x-card title="Ausencias" description="Vacaciones, incapacidades o permisos. Bloquean la agenda.">
            <form wire:submit="addTimeOff" class="space-y-3">
                <x-input label="Desde" type="datetime-local" wire:model="offFrom" required />
                <x-input label="Hasta" type="datetime-local" wire:model="offUntil" required />
                <x-input label="Motivo (opcional)" wire:model="offReason" maxlength="150" hint="No escriba diagnósticos." />
                <x-button type="submit" size="sm" icon="plus">Registrar ausencia</x-button>
            </form>

            <ul class="mt-5 divide-y divide-steel-200">
                @forelse ($timeOff as $off)
                    <li wire:key="off-{{ $off->id }}" class="flex items-center justify-between gap-3 py-2.5 text-sm">
                        <div>
                            <p class="font-semibold text-ink-950"><x-datetime :value="$off->starts_at" /> – <x-datetime :value="$off->ends_at" /></p>
                            @if ($off->reason)<p class="text-steel-700">{{ $off->reason }}</p>@endif
                        </div>
                        <x-button variant="ghost" size="sm" class="text-danger-700" wire:click="removeTimeOff({{ $off->id }})" wire:confirm="¿Eliminar la ausencia?">Quitar</x-button>
                    </li>
                @empty
                    <li class="py-2.5 text-sm text-steel-700">Sin ausencias próximas.</li>
                @endforelse
            </ul>
        </x-card>
    </div>
</div>
