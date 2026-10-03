<div class="space-y-6">
    <x-card title="Programas" :padding="false">
        <x-slot:actions>
            @can('create', [\App\Domain\Training\Models\TrainingProgram::class, $member])
                <x-button size="sm" icon="plus" wire:click="openCreate">Nuevo programa</x-button>
            @endcan
        </x-slot:actions>
        @if ($programs->isEmpty())
            <x-empty-state icon="bolt" title="Sin programas" description="Cree un programa en blanco o desde una plantilla." />
        @else
            <ul class="divide-y divide-steel-200">
                @foreach ($programs as $program)
                    <li wire:key="prog-{{ $program->id }}" class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 sm:px-6">
                        <div class="min-w-0">
                            <a href="{{ route('admin.training.programs.edit', $program) }}" wire:navigate class="font-semibold text-ink-950 hover:text-brand-700">{{ $program->name }}</a>
                            <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-steel-700">
                                <x-badge :color="$program->status->color()" dot>{{ $program->status->label() }}</x-badge>
                                @if ($program->isRehab())<x-badge color="info">Rehabilitación</x-badge>@endif
                                <span>{{ $program->workouts_count }} {{ $program->workouts_count === 1 ? 'rutina' : 'rutinas' }}</span>
                                <span>· {{ $program->staff?->full_name }}</span>
                                @if ($program->starts_on)<span>· desde {{ $program->starts_on->format('d/m/Y') }}</span>@endif
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-1">
                            @can('log', $program)
                                <x-button variant="secondary" size="sm" icon="check-circle" :href="route('admin.training.programs.log', $program)" wire:navigate>Registrar</x-button>
                            @endcan
                            <x-button variant="ghost" size="sm" icon="printer" :href="route('admin.training.programs.pdf', $program)" target="_blank">PDF</x-button>
                            @if (! $program->isRehab())
                                @can('create', \App\Domain\Training\Models\TrainingProgram::class)
                                    <x-button variant="ghost" size="sm" wire:click="openCopy({{ $program->id }})">Copiar a otro cliente</x-button>
                                @endcan
                            @endif
                            @can('delete', $program)
                                <x-button variant="ghost" size="sm" class="text-danger-700" wire:click="delete({{ $program->id }})" wire:confirm="¿Eliminar el programa {{ $program->name }}? El historial registrado se conserva.">Eliminar</x-button>
                            @endcan
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>

    <div class="grid gap-6 lg:grid-cols-5">
        <x-card title="Progreso por ejercicio" class="lg:col-span-3">
            @if ($exercises->isEmpty())
                <p class="text-sm text-steel-700">Aún no hay entrenamientos registrados.</p>
            @else
                <x-select label="Ejercicio" wire:model.live="progressExercise" :options="$exercises->mapWithKeys(fn ($e) => [$e->id => $e->name.' ('.$e->sessions.')'])->all()" />
                @php
                    $points = $series->values();
                    $weights = $points->pluck('max_weight')->filter(fn ($v) => $v !== null);
                    $volumes = $points->pluck('volume');
                    $w = 600; $h = 180; $pad = 24;
                    $count = max($points->count() - 1, 1);
                    $line = function ($values, $max) use ($w, $h, $pad, $count) {
                        return $values->map(fn ($v, $i) => $v === null ? null : round($pad + ($w - 2 * $pad) * $i / $count, 1).','.round($h - $pad - ($h - 2 * $pad) * ($max > 0 ? $v / $max : 0), 1))->filter()->implode(' ');
                    };
                    $maxWeight = (float) ($weights->max() ?? 0);
                    $maxVolume = (float) ($volumes->max() ?? 0);
                @endphp
                @if ($points->isNotEmpty())
                    <div class="mt-4 grid grid-cols-3 gap-3 text-center">
                        <div class="rounded-lg bg-canvas p-3"><p class="text-xs font-bold uppercase text-steel-700">Peso máx.</p><p class="font-display text-2xl font-bold text-ink-950">{{ $maxWeight ? rtrim(rtrim(number_format($maxWeight, 2, ',', '.'), '0'), ',').' kg' : '—' }}</p></div>
                        <div class="rounded-lg bg-canvas p-3"><p class="text-xs font-bold uppercase text-steel-700">Volumen máx.</p><p class="font-display text-2xl font-bold text-ink-950">{{ number_format($maxVolume, 0, ',', '.') }} kg</p></div>
                        <div class="rounded-lg bg-canvas p-3"><p class="text-xs font-bold uppercase text-steel-700">Sesiones</p><p class="font-display text-2xl font-bold text-ink-950">{{ $points->count() }}</p></div>
                    </div>
                    @if ($points->count() > 1)
                        <svg viewBox="0 0 {{ $w }} {{ $h }}" class="mt-4 w-full" role="img" aria-label="Evolución de {{ $selected->name }}">
                            <line x1="{{ $pad }}" y1="{{ $h - $pad }}" x2="{{ $w - $pad }}" y2="{{ $h - $pad }}" class="stroke-steel-200" stroke-width="1" />
                            <polyline points="{{ $line($volumes, $maxVolume) }}" fill="none" class="stroke-steel-300" stroke-width="2" stroke-dasharray="4 4" />
                            @if ($maxWeight > 0)
                                <polyline points="{{ $line($points->pluck('max_weight'), $maxWeight) }}" fill="none" class="stroke-brand-500" stroke-width="3" stroke-linejoin="round" />
                            @endif
                        </svg>
                        <div class="mt-1 flex justify-between text-xs text-steel-700">
                            <span>{{ \Carbon\CarbonImmutable::parse($points->first()->date)->format('d/m/Y') }}</span>
                            <span><span class="font-bold text-brand-700">—</span> peso máx. · <span class="font-bold text-steel-500">- -</span> volumen</span>
                            <span>{{ \Carbon\CarbonImmutable::parse($points->last()->date)->format('d/m/Y') }}</span>
                        </div>
                    @endif
                @endif
            @endif
        </x-card>

        <x-card title="Últimos entrenamientos" class="lg:col-span-2" :padding="false">
            @if ($logs->isEmpty())
                <p class="px-6 py-5 text-sm text-steel-700">Sin entrenamientos registrados.</p>
            @else
                <ul class="divide-y divide-steel-200">
                    @foreach ($logs as $log)
                        <li wire:key="log-{{ $log->id }}" class="px-5 py-3 text-sm sm:px-6">
                            <p class="font-semibold text-ink-950">{{ $log->workout?->name }} <span class="font-normal text-steel-700">· {{ $programNames[$log->workout?->training_program_id] ?? '' }}</span></p>
                            <p class="text-xs text-steel-700">
                                <x-datetime :value="$log->performed_at" /> · {{ $log->sets_count }} series
                                @if ($log->duration_minutes) · {{ $log->duration_minutes }} min @endif
                                @if ($log->rpe) · RPE {{ rtrim(rtrim(number_format($log->rpe, 1, ',', ''), '0'), ',') }} @endif
                                · {{ $log->logger?->name }}
                            </p>
                            @if ($log->notes)<p class="mt-1 text-xs text-ink-950">{{ $log->notes }}</p>@endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>

    <x-modal wire:model="showCreate" title="Nuevo programa" max-width="md">
        <form wire:submit="create" id="program-create-form" class="space-y-4">
            <x-select label="Partir de" wire:model.live="templateId" :options="$templates->all()" placeholder="Programa en blanco" />
            <x-input label="Nombre" wire:model="name" maxlength="150" :placeholder="$templateId !== '' ? 'Igual que la plantilla' : 'Fuerza base 3 días'" :required="$templateId === ''" />
            <x-textarea label="Objetivo" wire:model="goal" rows="2" />
            <x-input label="Inicio" type="date" wire:model="startsOn" />
            <p class="text-xs text-steel-700">Los ejercicios de rehabilitación se crean desde la historia clínica.</p>
        </form>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="open = false">Cancelar</x-button>
            <x-button type="submit" form="program-create-form">Crear y editar</x-button>
        </x-slot:footer>
    </x-modal>

    <x-modal wire:model="showCopy" title="Copiar a otro cliente" max-width="md">
        <x-input label="Buscar cliente" wire:model.live.debounce.300ms="copySearch" placeholder="Nombre, número o documento" autocomplete="off" />
        <ul class="mt-3 max-h-72 divide-y divide-steel-200 overflow-y-auto">
            @forelse ($copyResults as $result)
                <li wire:key="cp-{{ $result->id }}">
                    <button type="button" wire:click="copyTo({{ $result->id }})" class="flex w-full items-center justify-between px-2 py-2.5 text-left hover:bg-canvas">
                        <span class="font-semibold text-ink-950">{{ $result->full_name }}</span>
                        <span class="font-mono text-xs text-steel-700">{{ $result->member_number }}</span>
                    </button>
                </li>
            @empty
                <li class="px-2 py-3 text-sm text-steel-700">{{ mb_strlen(trim($copySearch)) < 2 ? 'Escriba al menos 2 caracteres.' : 'Sin resultados.' }}</li>
            @endforelse
        </ul>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="open = false">Cancelar</x-button>
        </x-slot:footer>
    </x-modal>
</div>
