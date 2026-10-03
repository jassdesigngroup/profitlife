<div>
    <x-page-header eyebrow="Entrenamiento" title="Ejercicios" description="Biblioteca común a todas las sedes para armar programas.">
        <x-slot:actions>
            <x-button variant="secondary" icon="squares" :href="route('admin.training.templates')" wire:navigate>Plantillas</x-button>
            @can('create', \App\Domain\Training\Models\Exercise::class)
                <x-button icon="plus" wire:click="create">Nuevo ejercicio</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-input label="Buscar" wire:model.live.debounce.300ms="search" placeholder="Nombre del ejercicio" autocomplete="off" class="lg:col-span-2" />
        <x-select label="Grupo muscular" wire:model.live="muscle" :options="$muscleGroups" placeholder="Todos" />
        <x-select label="Equipo" wire:model.live="equipmentFilter" :options="$equipmentList" placeholder="Todos" />
    </div>
    <x-checkbox label="Mostrar inactivos" wire:model.live="showInactive" class="mb-5" />

    @if ($exercises->isEmpty())
        <x-card><x-empty-state icon="bolt" title="Sin ejercicios" description="No hay ejercicios con esos filtros." /></x-card>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($exercises as $exercise)
                <button type="button" wire:key="ex-{{ $exercise->id }}" wire:click="view({{ $exercise->id }})"
                        class="flex gap-4 rounded-xl bg-surface p-4 text-left shadow-sm ring-1 ring-steel-200 transition hover:ring-brand-500">
                    <div class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-steel-100 text-steel-500">
                        @if ($exercise->image_path)
                            <img src="{{ route('admin.training.exercises.image', $exercise->id) }}?v={{ md5($exercise->image_path) }}" alt="" class="size-full object-cover" loading="lazy">
                        @else
                            <x-icon name="bolt" class="size-7" />
                        @endif
                    </div>
                    <div class="min-w-0">
                        <p class="font-semibold text-ink-950">{{ $exercise->name }}</p>
                        <p class="mt-0.5 truncate text-xs text-steel-700">{{ $exercise->muscleGroups->pluck('name')->implode(', ') }}</p>
                        <div class="mt-1.5 flex flex-wrap gap-1">
                            @foreach ($exercise->equipment->take(2) as $item)<x-badge color="neutral">{{ $item->name }}</x-badge>@endforeach
                            @if ($exercise->video_url)<x-badge color="info">Video</x-badge>@endif
                            @unless ($exercise->is_active)<x-badge color="warning">Inactivo</x-badge>@endunless
                        </div>
                    </div>
                </button>
            @endforeach
        </div>
        <div class="mt-5">{{ $exercises->links() }}</div>
    @endif

    <x-modal wire:model="showView" :title="$viewing?->name ?? 'Ejercicio'" max-width="2xl">
        @if ($viewing)
            <div class="space-y-4">
                @if ($viewing->embedUrl())
                    <div class="aspect-video overflow-hidden rounded-lg bg-ink-950">
                        <iframe src="{{ $viewing->embedUrl() }}" title="Video de {{ $viewing->name }}" class="size-full" loading="lazy" referrerpolicy="strict-origin-when-cross-origin"
                                allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen" allowfullscreen></iframe>
                    </div>
                @endif
                @if ($viewing->image_path)
                    <img src="{{ route('admin.training.exercises.image', $viewing->id) }}?v={{ md5($viewing->image_path) }}" alt="Imagen de {{ $viewing->name }}" class="max-h-72 rounded-lg object-contain">
                @endif
                <dl class="grid gap-4 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-bold uppercase tracking-wider text-steel-700">Músculos principales</dt>
                        <dd class="mt-1">{{ $viewing->muscleGroups->where('pivot.is_primary', true)->pluck('name')->implode(', ') ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-bold uppercase tracking-wider text-steel-700">Secundarios</dt>
                        <dd class="mt-1">{{ $viewing->muscleGroups->where('pivot.is_primary', false)->pluck('name')->implode(', ') ?: '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-bold uppercase tracking-wider text-steel-700">Equipo</dt>
                        <dd class="mt-1">{{ $viewing->equipment->pluck('name')->implode(', ') ?: 'Sin equipo' }}</dd>
                    </div>
                </dl>
                @if ($viewing->description)<p class="text-sm text-ink-950">{{ $viewing->description }}</p>@endif
                @if ($viewing->instructions)
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-steel-700">Ejecución</p>
                        <p class="mt-1 whitespace-pre-line text-sm text-ink-950">{{ $viewing->instructions }}</p>
                    </div>
                @endif
            </div>
            <x-slot:footer>
                @can('delete', $viewing)
                    <x-button variant="ghost" class="text-danger-700 sm:mr-auto" wire:click="delete({{ $viewing->id }})" wire:confirm="¿Eliminar {{ $viewing->name }} de la biblioteca? Los programas que lo usan lo conservan.">Eliminar</x-button>
                @endcan
                @can('update', $viewing)
                    <x-button variant="secondary" icon="pencil" wire:click="edit({{ $viewing->id }})">Editar</x-button>
                @endcan
                <x-button x-on:click="open = false">Cerrar</x-button>
            </x-slot:footer>
        @endif
    </x-modal>

    <x-modal wire:model="showForm" :title="$editingId ? 'Editar ejercicio' : 'Nuevo ejercicio'" max-width="2xl">
        <form wire:submit="save" id="exercise-form" class="space-y-4">
            <x-input label="Nombre" wire:model="name" maxlength="150" required />
            <x-textarea label="Descripción" wire:model="description" rows="2" />
            <x-textarea label="Ejecución (instrucciones)" wire:model="instructions" rows="4" />
            <x-input label="Video (YouTube o Vimeo)" type="url" wire:model="videoUrl" placeholder="https://www.youtube.com/watch?v=…" />

            <fieldset>
                <legend class="mb-1.5 text-sm font-semibold text-ink-950">Grupos musculares <span class="text-brand-700">*</span></legend>
                <div class="grid grid-cols-[1fr_auto_auto] items-center gap-x-4 gap-y-1 text-sm">
                    <span class="text-xs font-bold uppercase text-steel-700">Grupo</span><span class="text-xs font-bold uppercase text-steel-700">Principal</span><span class="text-xs font-bold uppercase text-steel-700">Secundario</span>
                    @foreach ($muscleGroups as $id => $label)
                        <span>{{ $label }}</span>
                        <input type="checkbox" value="{{ $id }}" wire:model="primary" class="size-4 justify-self-center accent-brand-500" aria-label="{{ $label }} principal">
                        <input type="checkbox" value="{{ $id }}" wire:model="secondary" class="size-4 justify-self-center accent-brand-500" aria-label="{{ $label }} secundario">
                    @endforeach
                </div>
                <x-field-error name="primary" />
            </fieldset>

            <fieldset>
                <legend class="mb-1.5 text-sm font-semibold text-ink-950">Equipo</legend>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                    @foreach ($equipmentList as $id => $label)
                        <x-checkbox :label="$label" value="{{ $id }}" wire:model="equipmentIds" />
                    @endforeach
                </div>
            </fieldset>

            <div>
                <label class="mb-1.5 block text-sm font-semibold text-ink-950" for="exercise-image">Imagen</label>
                <input id="exercise-image" type="file" accept="image/jpeg,image/png,image/webp" wire:model="image" class="block w-full text-sm">
                <x-field-error name="image" />
                @if ($editingImage)
                    <x-checkbox label="Quitar la imagen actual" wire:model="removeImage" class="mt-2" />
                @endif
            </div>
            <x-checkbox label="Activo" description="Los inactivos no aparecen al armar programas." wire:model="isActive" />
        </form>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="open = false">Cancelar</x-button>
            <x-button type="submit" form="exercise-form" wire:loading.attr="disabled">Guardar</x-button>
        </x-slot:footer>
    </x-modal>
</div>
