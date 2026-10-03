<div>
    <x-page-header eyebrow="Entrenamiento" title="Plantillas" description="Programas base para asignar a cualquier cliente desde su ficha.">
        <x-slot:actions>
            <x-button variant="secondary" icon="bolt" :href="route('admin.training.exercises')" wire:navigate>Ejercicios</x-button>
            @can('create', \App\Domain\Training\Models\TrainingProgram::class)
                <x-button icon="plus" wire:click="create">Nueva plantilla</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-input label="Buscar" wire:model.live.debounce.300ms="search" placeholder="Nombre de la plantilla" autocomplete="off" class="mb-5 max-w-md" />

    <x-card :padding="false">
        @if ($templates->isEmpty())
            <x-empty-state icon="squares" title="Sin plantillas" description="Cree una plantilla o guarde el programa de un cliente como plantilla." />
        @else
            <x-table>
                <x-slot:head>
                    <th scope="col" class="px-6 py-3">Plantilla</th>
                    <th scope="col" class="px-4 py-3 text-center">Rutinas</th>
                    <th scope="col" class="px-4 py-3">Creada por</th>
                    <th scope="col" class="px-4 py-3">Estado</th>
                    <th scope="col" class="px-6 py-3"><span class="sr-only">Acciones</span></th>
                </x-slot:head>
                @foreach ($templates as $template)
                    <tr wire:key="tpl-{{ $template->id }}" class="hover:bg-canvas">
                        <td class="px-6 py-4">
                            <a href="{{ route('admin.training.programs.edit', $template) }}" wire:navigate class="font-semibold text-ink-950 hover:text-brand-700">{{ $template->name }}</a>
                            @if ($template->goal)<p class="max-w-md truncate text-xs text-steel-700">{{ $template->goal }}</p>@endif
                        </td>
                        <td class="px-4 py-4 text-center">{{ $template->workouts_count }}</td>
                        <td class="px-4 py-4 text-steel-700">{{ $template->staff?->full_name }}</td>
                        <td class="px-4 py-4"><x-badge :color="$template->status->color()" dot>{{ $template->status->label() }}</x-badge></td>
                        <td class="px-6 py-4 text-right">
                            <x-button variant="ghost" size="sm" :href="route('admin.training.programs.edit', $template)" wire:navigate>Abrir</x-button>
                            @can('delete', $template)
                                <x-button variant="ghost" size="sm" class="text-danger-700" wire:click="delete({{ $template->id }})" wire:confirm="¿Eliminar la plantilla {{ $template->name }}? Los programas ya asignados no cambian.">Eliminar</x-button>
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>

    <x-modal wire:model="showForm" title="Nueva plantilla" max-width="md">
        <form wire:submit="save" id="template-new-form" class="space-y-4">
            <x-input label="Nombre" wire:model="name" maxlength="150" placeholder="Hipertrofia 4 días" required />
            <x-textarea label="Objetivo" wire:model="goal" rows="2" />
        </form>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="open = false">Cancelar</x-button>
            <x-button type="submit" form="template-new-form">Crear y editar</x-button>
        </x-slot:footer>
    </x-modal>
</div>
