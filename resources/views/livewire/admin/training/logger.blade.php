<div class="mx-auto max-w-3xl">
    <a href="{{ route('admin.training.programs.edit', $program) }}" wire:navigate class="mb-4 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-700 hover:underline">
        <x-icon name="arrow-left" class="size-4" /> {{ $program->name }}
    </a>
    <x-page-header eyebrow="Registrar entrenamiento" :title="$program->member?->full_name ?? ''" :description="$program->name" />

    @if ($program->workouts->isEmpty())
        <x-card><x-empty-state icon="bolt" title="El programa no tiene rutinas" description="Agregue rutinas en el editor del programa." /></x-card>
    @else
        <nav class="mb-4 flex gap-2 overflow-x-auto pb-1" aria-label="Rutinas">
            @foreach ($program->workouts as $item)
                <button type="button" wire:key="wk-{{ $item->id }}" wire:click="selectWorkout({{ $item->id }})" @class([
                    'whitespace-nowrap rounded-full px-4 py-2 text-sm font-bold ring-1',
                    'bg-brand-500 text-white ring-brand-500' => $workout?->id === $item->id,
                    'bg-surface text-ink-950 ring-steel-300 hover:bg-steel-100' => $workout?->id !== $item->id,
                ]) @if ($workout?->id === $item->id) aria-current="true" @endif>{{ $item->name }}</button>
            @endforeach
        </nav>

        <form wire:submit="save" class="space-y-4">
            @foreach ($workout?->exercises ?? [] as $item)
                <x-card wire:key="it-{{ $item->id }}" :padding="false">
                    <div class="flex items-start justify-between gap-3 border-b border-steel-200 px-4 py-3 sm:px-6">
                        <div>
                            <p class="font-semibold text-ink-950">{{ $item->exercise?->name }}
                                @if ($item->superset_group)<x-badge color="warning" class="ml-1">Superserie {{ $item->superset_group }}</x-badge>@endif
                            </p>
                            <p class="text-xs text-steel-700">
                                Prescrito: {{ $item->sets->count() }} series
                                @if ($item->sets->first()?->rest_seconds) · descanso {{ $item->sets->first()->rest_seconds }} s @endif
                                @if ($item->notes) · {{ $item->notes }} @endif
                            </p>
                            @if ($previous->has($item->id))
                                <p class="mt-0.5 text-xs text-brand-700">Última vez: {{ $previous[$item->id] }}</p>
                            @endif
                        </div>
                    </div>
                    <div class="divide-y divide-steel-100 px-4 sm:px-6">
                        @foreach ($sets[$item->id] ?? [] as $s => $set)
                            <div wire:key="ls-{{ $item->id }}-{{ $s }}" class="flex items-center gap-2 py-2">
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" wire:model="sets.{{ $item->id }}.{{ $s }}.done" class="size-6 accent-brand-500" aria-label="Serie {{ $s + 1 }} hecha">
                                    <span class="w-6 text-sm font-bold text-steel-700">{{ $s + 1 }}</span>
                                </label>
                                <div class="grid flex-1 grid-cols-3 gap-2">
                                    <label class="text-[11px] font-bold uppercase text-steel-700">Reps
                                        <input type="text" inputmode="numeric" wire:model="sets.{{ $item->id }}.{{ $s }}.reps" class="mt-0.5 block w-full rounded-md border-0 px-2 py-2 text-base ring-1 ring-steel-300 focus:ring-2 focus:ring-brand-500">
                                    </label>
                                    <label class="text-[11px] font-bold uppercase text-steel-700">Kg
                                        <input type="text" inputmode="decimal" wire:model="sets.{{ $item->id }}.{{ $s }}.weight_kg" class="mt-0.5 block w-full rounded-md border-0 px-2 py-2 text-base ring-1 ring-steel-300 focus:ring-2 focus:ring-brand-500">
                                    </label>
                                    @if ($item->sets->contains(fn ($p) => $p->duration_seconds !== null || $p->distance_meters !== null))
                                        <label class="text-[11px] font-bold uppercase text-steel-700">Seg
                                            <input type="text" inputmode="numeric" wire:model="sets.{{ $item->id }}.{{ $s }}.duration_seconds" class="mt-0.5 block w-full rounded-md border-0 px-2 py-2 text-base ring-1 ring-steel-300 focus:ring-2 focus:ring-brand-500">
                                        </label>
                                    @else
                                        <label class="text-[11px] font-bold uppercase text-steel-700">RPE
                                            <input type="text" inputmode="decimal" wire:model="sets.{{ $item->id }}.{{ $s }}.rpe" class="mt-0.5 block w-full rounded-md border-0 px-2 py-2 text-base ring-1 ring-steel-300 focus:ring-2 focus:ring-brand-500">
                                        </label>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="border-t border-steel-200 px-4 py-2 sm:px-6">
                        <x-button variant="ghost" size="sm" icon="plus" wire:click="addSet({{ $item->id }})">Serie extra</x-button>
                    </div>
                </x-card>
            @endforeach

            <x-card title="Sesión">
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-input label="Fecha y hora" type="datetime-local" wire:model="performedAt" required />
                    <x-input label="Duración (min)" type="number" min="1" max="600" wire:model="duration" />
                    <x-input label="RPE de la sesión (1-10)" type="number" step="0.5" min="1" max="10" wire:model="rpe" />
                    <x-textarea label="Notas" wire:model="notes" rows="2" class="sm:col-span-3" placeholder="Cómo se sintió, molestias, ajustes para la próxima…" />
                </div>
            </x-card>

            <x-field-error name="sets" />
            <x-field-error name="workoutId" />
            @if ($errors->any())
                <x-alert type="danger">{{ collect($errors->all())->take(3)->implode(' ') }}</x-alert>
            @endif

            <div class="sticky bottom-0 z-10 -mx-4 border-t border-steel-200 bg-canvas/95 px-4 py-3 backdrop-blur sm:mx-0 sm:rounded-xl sm:border">
                <x-button type="submit" icon="check" class="w-full justify-center py-3.5 text-base" wire:loading.attr="disabled">Guardar entrenamiento</x-button>
            </div>
        </form>
    @endif
</div>
