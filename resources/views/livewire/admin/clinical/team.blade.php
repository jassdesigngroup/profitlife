<x-card title="Equipo tratante" description="Solo estas personas ven el contenido clínico.">
    <ul class="divide-y divide-steel-200">
        @foreach ($record->activeTeam as $member)
            <li wire:key="tm-{{ $member->staff_id }}" class="flex items-center justify-between gap-2 py-2 text-sm">
                <div>
                    <p class="font-semibold text-ink-950">{{ $member->staff?->full_name }}</p>
                    <p class="text-xs text-steel-700">{{ $member->staff_id === $record->primary_staff_id ? 'Responsable' : 'Desde '.$member->granted_at->format('d/m/Y') }}</p>
                </div>
                @if ($canManage && $member->staff_id !== $record->primary_staff_id)
                    <x-button size="sm" variant="ghost" class="text-danger-700" wire:click="revoke({{ $member->staff_id }})" wire:confirm="¿Retirar a {{ $member->staff?->full_name }} del equipo? Perderá el acceso de inmediato.">Retirar</x-button>
                @endif
            </li>
        @endforeach
    </ul>
    @if ($canManage && $candidates)
        <form wire:submit="add" class="mt-3 flex items-end gap-2">
            <div class="min-w-0 flex-1"><x-select label="Agregar profesional" wire:model="newStaffId" :options="$candidates" placeholder="Seleccione" /></div>
            <x-button type="submit" size="sm" icon="plus">Agregar</x-button>
        </form>
    @endif
</x-card>
