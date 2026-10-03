<x-card title="Ejercicios para casa" description="Programas de rehabilitación del paciente.">
    @can('create', [\App\Domain\Training\Models\TrainingProgram::class, $record->member, \App\Domain\Training\Enums\ProgramType::Rehab])
        <x-slot:actions><x-button size="sm" variant="secondary" icon="plus" wire:click="create">Nuevo</x-button></x-slot:actions>
    @endcan
    @forelse ($programs as $program)
        <div wire:key="rehab-{{ $program->id }}" class="border-b border-steel-200 py-3 text-sm last:border-0">
            <div class="flex items-start justify-between gap-2">
                <a href="{{ route('admin.training.programs.edit', $program) }}" wire:navigate class="font-semibold text-ink-950 hover:text-brand-700">{{ $program->name }}</a>
                <x-badge :color="$program->status->color()">{{ $program->status->label() }}</x-badge>
            </div>
            <p class="text-steel-700">{{ $program->workouts_count }} {{ $program->workouts_count === 1 ? 'rutina' : 'rutinas' }}@if ($program->treatmentPlan) · {{ $program->treatmentPlan->title }}@endif</p>
            <div class="mt-1 flex gap-1">
                <x-button size="sm" variant="ghost" :href="route('admin.training.programs.edit', $program)" wire:navigate>Abrir</x-button>
                <x-button size="sm" variant="ghost" icon="printer" :href="route('admin.training.programs.pdf', $program)" target="_blank">PDF</x-button>
            </div>
        </div>
    @empty
        <p class="text-sm text-steel-700">Sin ejercicios para casa.</p>
    @endforelse

    <x-modal wire:model="showForm" title="Ejercicios para casa" max-width="md">
        <form wire:submit="save" id="rehab-form" class="space-y-4">
            <x-input label="Nombre" wire:model="name" maxlength="150" placeholder="Fortalecimiento de rodilla" required />
            <x-select label="Plan de tratamiento" wire:model="planId" :options="$plans->all()" placeholder="Sin plan" />
            <x-textarea label="Indicaciones" wire:model="goal" rows="3" placeholder="Frecuencia, precauciones, cuándo suspender…" />
        </form>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="open = false">Cancelar</x-button>
            <x-button type="submit" form="rehab-form">Crear y agregar ejercicios</x-button>
        </x-slot:footer>
    </x-modal>
</x-card>
