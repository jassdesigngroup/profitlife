<div>
    @if ($program->member)
        <a href="{{ route('admin.members.show', ['member' => $program->member_id, 'tab' => 'training']) }}" wire:navigate class="mb-4 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-700 hover:underline">
            <x-icon name="arrow-left" class="size-4" /> {{ $program->member->full_name }}
        </a>
    @else
        <a href="{{ route('admin.training.templates') }}" wire:navigate class="mb-4 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-700 hover:underline">
            <x-icon name="arrow-left" class="size-4" /> Plantillas
        </a>
    @endif

    <x-page-header :eyebrow="$program->is_template ? 'Plantilla' : $program->type->label()" :title="$program->name" :description="'Prescrito por '.$program->staff?->full_name.($program->treatmentPlan ? ' · Plan: '.$program->treatmentPlan->title : '')">
        <x-slot:actions>
            @unless ($program->is_template)
                @can('log', $program)
                    <x-button variant="secondary" icon="check-circle" :href="route('admin.training.programs.log', $program)" wire:navigate>Registrar entrenamiento</x-button>
                @endcan
                <x-button variant="secondary" icon="printer" :href="route('admin.training.programs.pdf', $program)" target="_blank">PDF</x-button>
                <x-button variant="secondary" icon="envelope" wire:click="send" wire:confirm="¿Enviar el programa en PDF al correo del cliente?" :disabled="blank($program->member?->email)">Enviar</x-button>
            @else
                <x-button variant="secondary" icon="printer" :href="route('admin.training.programs.pdf', $program)" target="_blank">PDF</x-button>
            @endunless
            @if ($program->type === \App\Domain\Training\Enums\ProgramType::Training)
                @can('create', \App\Domain\Training\Models\TrainingProgram::class)
                    <x-button variant="ghost" wire:click="openTemplate">Guardar como plantilla</x-button>
                @endcan
            @endif
        </x-slot:actions>
    </x-page-header>
    <x-field-error name="send" />

    <form wire:submit="save" class="space-y-6">
        <x-card title="Programa">
            <fieldset @disabled(! $canEdit) class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-input label="Nombre" wire:model="name" maxlength="150" required class="sm:col-span-2" />
                <x-select label="Estado" wire:model="status" :options="$statuses" />
                <div></div>
                @unless ($program->is_template)
                    <x-input label="Inicio" type="date" wire:model="startsOn" />
                    <x-input label="Fin" type="date" wire:model="endsOn" />
                @endunless
                <x-textarea label="Objetivo" wire:model="goal" rows="2" class="sm:col-span-2" />
            </fieldset>
        </x-card>

        @foreach ($workouts as $w => $workout)
            <x-card wire:key="w-{{ $w }}-{{ $workout['id'] ?? 'new' }}" :padding="false">
                <div class="flex flex-wrap items-end gap-3 border-b border-steel-200 px-6 py-4">
                    <div class="min-w-0 flex-1"><x-input label="Rutina" wire:model.blur="workouts.{{ $w }}.name" maxlength="150" :disabled="! $canEdit" /></div>
                    <div class="min-w-0 flex-1"><x-input label="Notas" wire:model.blur="workouts.{{ $w }}.notes" placeholder="Calentamiento, indicaciones…" :disabled="! $canEdit" /></div>
                    @if ($canEdit)
                        <div class="flex gap-1">
                            <x-button variant="ghost" size="sm" wire:click="moveWorkout({{ $w }}, -1)" aria-label="Subir rutina">↑</x-button>
                            <x-button variant="ghost" size="sm" wire:click="moveWorkout({{ $w }}, 1)" aria-label="Bajar rutina">↓</x-button>
                            <x-button variant="ghost" size="sm" class="text-danger-700" wire:click="removeWorkout({{ $w }})" wire:confirm="¿Quitar la rutina {{ $workout['name'] }}?" aria-label="Quitar rutina"><x-icon name="trash" class="size-4" /></x-button>
                        </div>
                    @endif
                </div>

                <div class="divide-y divide-steel-200">
                    @foreach ($workout['exercises'] as $e => $item)
                        <div wire:key="e-{{ $w }}-{{ $e }}-{{ $item['id'] ?? 'n' }}" class="px-6 py-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="font-semibold text-ink-950">
                                    <span class="mr-1 text-steel-500">{{ $e + 1 }}.</span> {{ $item['name'] }}
                                    @if ($item['superset_group'] !== '')<x-badge color="warning" class="ml-1">Superserie {{ $item['superset_group'] }}</x-badge>@endif
                                </p>
                                @if ($canEdit)
                                    <div class="flex items-center gap-1">
                                        <label class="flex items-center gap-1 text-xs text-steel-700">Superserie
                                            <input type="number" min="1" max="20" wire:model.blur="workouts.{{ $w }}.exercises.{{ $e }}.superset_group" class="w-14 rounded-md border-0 px-2 py-1 text-sm ring-1 ring-steel-300" aria-label="Grupo de superserie">
                                        </label>
                                        <x-button variant="ghost" size="sm" wire:click="moveExercise({{ $w }}, {{ $e }}, -1)" aria-label="Subir">↑</x-button>
                                        <x-button variant="ghost" size="sm" wire:click="moveExercise({{ $w }}, {{ $e }}, 1)" aria-label="Bajar">↓</x-button>
                                        <x-button variant="ghost" size="sm" class="text-danger-700" wire:click="removeExercise({{ $w }}, {{ $e }})" aria-label="Quitar ejercicio"><x-icon name="trash" class="size-4" /></x-button>
                                    </div>
                                @endif
                            </div>

                            <div class="mt-2 overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead class="text-left text-[11px] font-bold uppercase tracking-wider text-steel-700">
                                        <tr><th class="py-1 pr-2">Serie</th><th class="px-1">Reps</th><th class="px-1">Peso kg</th><th class="px-1">Tiempo s</th><th class="px-1">Distancia m</th><th class="px-1">Descanso s</th><th class="px-1">RPE</th><th class="px-1">Nota</th><th></th></tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($item['sets'] as $s => $set)
                                            <tr wire:key="s-{{ $w }}-{{ $e }}-{{ $s }}">
                                                <td class="py-1 pr-2 font-semibold text-steel-700">{{ $s + 1 }}</td>
                                                @foreach (['reps' => 'w-16', 'weight_kg' => 'w-20', 'duration_seconds' => 'w-20', 'distance_meters' => 'w-24', 'rest_seconds' => 'w-20', 'rpe' => 'w-16', 'notes' => 'w-40'] as $field => $width)
                                                    <td class="px-1 py-1"><input type="{{ $field === 'notes' ? 'text' : 'text' }}" inputmode="{{ $field === 'notes' ? 'text' : 'decimal' }}" wire:model.blur="workouts.{{ $w }}.exercises.{{ $e }}.sets.{{ $s }}.{{ $field }}" @disabled(! $canEdit)
                                                        class="{{ $width }} rounded-md border-0 px-2 py-1 text-sm ring-1 ring-steel-300 focus:ring-2 focus:ring-brand-500 disabled:bg-steel-100" aria-label="{{ $field }} serie {{ $s + 1 }}"></td>
                                                @endforeach
                                                <td class="px-1">@if ($canEdit)<button type="button" wire:click="removeSet({{ $w }}, {{ $e }}, {{ $s }})" class="rounded p-1 text-steel-500 hover:text-danger-700" aria-label="Quitar serie"><x-icon name="x" class="size-4" /></button>@endif</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-2 flex flex-wrap items-center gap-3">
                                @if ($canEdit)<x-button variant="ghost" size="sm" icon="plus" wire:click="addSet({{ $w }}, {{ $e }})">Serie</x-button>@endif
                                <input type="text" wire:model.blur="workouts.{{ $w }}.exercises.{{ $e }}.notes" placeholder="Indicación del ejercicio (técnica, tempo…)" @disabled(! $canEdit) class="min-w-0 flex-1 rounded-md border-0 px-3 py-1.5 text-sm ring-1 ring-steel-300 disabled:bg-steel-100">
                            </div>
                        </div>
                    @endforeach
                </div>
                @if ($canEdit)
                    <div class="border-t border-steel-200 px-6 py-3">
                        <x-button variant="secondary" size="sm" icon="plus" wire:click="openPicker({{ $w }})">Agregar ejercicio</x-button>
                    </div>
                @endif
            </x-card>
        @endforeach

        <x-field-error name="workouts" />
        @if ($errors->any())
            <x-alert type="danger">Revise los campos marcados: {{ collect($errors->all())->take(3)->implode(' ') }}</x-alert>
        @endif

        @if ($canEdit)
            <div class="sticky bottom-0 z-10 -mx-4 flex flex-wrap items-center justify-between gap-3 border-t border-steel-200 bg-canvas/95 px-4 py-3 backdrop-blur sm:mx-0 sm:rounded-xl sm:border sm:px-6">
                <x-button variant="secondary" icon="plus" wire:click="addWorkout">Agregar rutina</x-button>
                <div class="flex items-center gap-3">
                    @if ($dirty)<span class="text-sm font-semibold text-warning-700">Cambios sin guardar</span>@endif
                    <x-button type="submit" icon="check" wire:loading.attr="disabled">Guardar programa</x-button>
                </div>
            </div>
        @endif
        @if ($hasLogs && $canEdit)
            <p class="text-xs text-steel-700">Las rutinas y ejercicios con entrenamientos registrados no se pueden quitar; sí puede cambiar sus series.</p>
        @endif
    </form>

    <x-modal wire:model="showPicker" title="Agregar ejercicio" max-width="xl">
        <x-input label="Buscar" wire:model.live.debounce.250ms="pickerSearch" placeholder="Sentadilla, remo, plancha…" autocomplete="off" />
        <ul class="mt-3 max-h-96 divide-y divide-steel-200 overflow-y-auto">
            @forelse ($pickerResults as $exercise)
                <li wire:key="pk-{{ $exercise->id }}">
                    <button type="button" wire:click="pickExercise({{ $exercise->id }})" class="flex w-full items-center justify-between gap-3 px-2 py-2.5 text-left hover:bg-canvas">
                        <span class="font-semibold text-ink-950">{{ $exercise->name }}</span>
                        <span class="text-xs text-steel-700">{{ $exercise->muscleGroups->pluck('name')->take(2)->implode(', ') }}</span>
                    </button>
                </li>
            @empty
                <li class="px-2 py-3 text-sm text-steel-700">Sin resultados. Puede crearlo en <a href="{{ route('admin.training.exercises') }}" class="font-semibold text-brand-700 hover:underline" target="_blank">Ejercicios</a>.</li>
            @endforelse
        </ul>
        <x-slot:footer>
            <x-button x-on:click="open = false">Listo</x-button>
        </x-slot:footer>
    </x-modal>

    <x-modal wire:model="showTemplate" title="Guardar como plantilla" max-width="md">
        <form wire:submit="saveAsTemplate" id="template-form" class="space-y-4">
            <p class="text-sm text-steel-700">Se copia la estructura (rutinas, ejercicios y series) sin datos del cliente.</p>
            <x-input label="Nombre de la plantilla" wire:model="templateName" required />
        </form>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="open = false">Cancelar</x-button>
            <x-button type="submit" form="template-form">Crear plantilla</x-button>
        </x-slot:footer>
    </x-modal>
</div>
