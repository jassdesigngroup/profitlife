<div>
    <x-card title="Horario semanal" description="Horas locales de la sede ({{ $location->timezone }}). Puede añadir varias franjas por día.">
        @if ($canManage && ! $editing)
            <x-slot:actions>
                <x-button variant="secondary" size="sm" icon="pencil" wire:click="edit">Editar horario</x-button>
            </x-slot:actions>
        @endif

        @if ($editing)
            <form wire:submit="save" class="space-y-4">
                @error('slots')<x-alert type="danger">{{ $message }}</x-alert>@enderror

                @forelse ($slots as $i => $slot)
                    <div wire:key="slot-{{ $i }}" class="grid grid-cols-2 items-start gap-3 rounded-lg bg-canvas p-3 sm:grid-cols-[1.4fr_1fr_1fr_auto]">
                        <x-select label="Día" wire:model="slots.{{ $i }}.day_of_week" :options="\App\Domain\Locations\Enums\DayOfWeek::options()" class="col-span-2 sm:col-span-1" />
                        <x-input label="Abre" type="time" wire:model="slots.{{ $i }}.opens_at" />
                        <x-input label="Cierra" type="time" wire:model="slots.{{ $i }}.closes_at" />
                        <div class="col-span-2 flex justify-end sm:col-span-1 sm:pt-7">
                            <x-button variant="ghost" size="sm" icon="trash" wire:click="removeSlot({{ $i }})" class="text-danger-700">Quitar</x-button>
                        </div>
                    </div>
                @empty
                    <x-empty-state icon="clock" title="Sin franjas" description="La sede figurará como cerrada todos los días." />
                @endforelse

                <div class="flex flex-col gap-2 border-t border-steel-200 pt-4 sm:flex-row sm:justify-between">
                    <x-button variant="secondary" icon="plus" wire:click="addSlot">Añadir franja</x-button>
                    <div class="flex gap-2">
                        <x-button variant="ghost" wire:click="cancel">Cancelar</x-button>
                        <x-button type="submit" wire:loading.attr="disabled">Guardar horario</x-button>
                    </div>
                </div>
            </form>
        @else
            <dl class="divide-y divide-steel-200">
                @foreach ($days as $day)
                    <div class="flex flex-col gap-1 py-3 sm:flex-row sm:items-center">
                        <dt class="w-32 text-sm font-semibold text-ink-950">{{ $day->label() }}</dt>
                        <dd class="flex flex-wrap gap-2">
                            @forelse ($byDay->get($day->value, collect()) as $hour)
                                <x-badge color="dark">{{ substr($hour->opens_at, 0, 5) }} – {{ substr($hour->closes_at, 0, 5) }}</x-badge>
                            @empty
                                <span class="text-sm text-steel-700">Cerrado</span>
                            @endforelse
                        </dd>
                    </div>
                @endforeach
            </dl>
        @endif
    </x-card>
</div>
