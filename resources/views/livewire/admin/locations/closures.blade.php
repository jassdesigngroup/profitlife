<div>
    <x-card title="Cierres y festivos" description="Días en que la sede no abre. Los cierres generales aplican a todas las sedes.">
        @if ($canManage)
            <x-slot:actions>
                <x-button size="sm" icon="plus" wire:click="create">Añadir cierre</x-button>
            </x-slot:actions>
        @endif

        @if ($closures->isEmpty())
            <x-empty-state icon="calendar" title="Sin cierres programados" description="Registre festivos o cierres puntuales para que no se agenden citas esos días." />
        @else
            <ul class="divide-y divide-steel-200">
                @foreach ($closures as $closure)
                    <li wire:key="closure-{{ $closure->id }}" class="flex items-center gap-4 py-3">
                        <div class="flex w-14 shrink-0 flex-col items-center rounded-lg bg-ink-950 py-1.5 text-white">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-brand-500">{{ $closure->closed_on->locale('es')->translatedFormat('M') }}</span>
                            <span class="font-display text-xl font-bold leading-none">{{ $closure->closed_on->format('d') }}</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-ink-950">{{ $closure->reason ?: 'Cierre' }}</p>
                            <p class="text-xs text-steel-700">{{ ucfirst($closure->closed_on->locale('es')->translatedFormat('l j \d\e F \d\e Y')) }}</p>
                        </div>
                        @if ($closure->isGlobal())
                            <x-badge color="info">Todas las sedes</x-badge>
                        @endif
                        @if ($closure->closed_on->isPast() && ! $closure->closed_on->isToday())
                            <x-badge>Pasado</x-badge>
                        @endif
                        @if ($closure->isGlobal() ? $canManageGlobal : $canManage)
                            <x-button variant="ghost" size="sm" icon="trash" class="text-danger-700" wire:click="remove({{ $closure->id }})" wire:confirm="¿Eliminar este cierre?">
                                <span class="sr-only">Eliminar</span>
                            </x-button>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>

    <x-modal wire:model="showForm" title="Nuevo cierre" max-width="md">
        <form wire:submit="save" id="closure-form" class="space-y-4">
            <x-input label="Fecha" type="date" wire:model="closedOn" required />
            <x-input label="Motivo" wire:model="reason" placeholder="Festivo, mantenimiento…" maxlength="150" />
            @if ($canManageGlobal)
                <x-checkbox wire:model="allLocations" label="Aplicar a todas las sedes" description="Útil para festivos nacionales." />
            @endif
        </form>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="open = false">Cancelar</x-button>
            <x-button type="submit" form="closure-form" wire:loading.attr="disabled">Guardar</x-button>
        </x-slot:footer>
    </x-modal>
</div>
