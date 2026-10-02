<div class="space-y-6">
    @if ($record)
        <x-card title="Historia clínica de fisioterapia" description="Datos administrativos. El contenido clínico solo lo ve el equipo tratante.">
            @if ($canOpenRecord)
                <x-slot:actions><x-button size="sm" icon="clipboard" :href="route('admin.clinical.show', $member)" wire:navigate>Abrir historia clínica</x-button></x-slot:actions>
            @endif
            <dl class="grid gap-4 text-sm sm:grid-cols-4">
                <div><dt class="text-xs font-bold uppercase tracking-wider text-steel-700">Estado</dt><dd class="mt-1"><x-badge :color="$record->status->color()" dot>{{ $record->status->label() }}</x-badge></dd></div>
                <div><dt class="text-xs font-bold uppercase tracking-wider text-steel-700">Responsable</dt><dd class="mt-1 font-semibold text-ink-950">{{ $record->primaryStaff?->full_name ?? '—' }}</dd></div>
                <div><dt class="text-xs font-bold uppercase tracking-wider text-steel-700">Sesiones</dt><dd class="mt-1 font-display text-2xl font-bold text-ink-950">{{ $record->sessions_count }}</dd></div>
                <div><dt class="text-xs font-bold uppercase tracking-wider text-steel-700">Última sesión</dt><dd class="mt-1 text-ink-950"><x-datetime :value="$lastSession ? \Illuminate\Support\Carbon::parse($lastSession) : null" format="d/m/Y" /></dd></div>
            </dl>
        </x-card>
        <livewire:admin.clinical.clinical-team :record-id="$record->id" :key="'team-tab-'.$record->id" />
    @else
        <x-card>
            <x-empty-state icon="clipboard" title="Sin historia clínica" description="Se abre con la evaluación inicial de fisioterapia." />
            @if ($canCreate)
                <div class="flex justify-center"><x-button icon="plus" wire:click="openForm">Abrir historia clínica</x-button></div>
            @endif
        </x-card>
    @endif

    <x-modal wire:model="showOpen" title="Abrir historia clínica" max-width="2xl">
        <form wire:submit="open" id="open-record-form" class="space-y-4">
            <p class="text-sm text-steel-700">Usted quedará como profesional responsable. Los antecedentes se pueden completar después.</p>
            <x-textarea label="Motivo de consulta" wire:model="reason" rows="2" />
            <x-textarea label="Historia médica" wire:model="history" rows="3" />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-textarea label="Medicamentos" wire:model="medications" rows="2" />
                <x-textarea label="Alergias" wire:model="allergies" rows="2" />
            </div>
        </form>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="open = false">Cancelar</x-button>
            <x-button type="submit" form="open-record-form" icon="check">Abrir historia</x-button>
        </x-slot:footer>
    </x-modal>
</div>
