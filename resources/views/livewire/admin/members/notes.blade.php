<div class="grid gap-6 lg:grid-cols-3">
    @can('update', $member)
        <x-card title="Nueva nota" description="Solo información administrativa. Nunca datos clínicos." class="lg:order-2">
            <form wire:submit="add" class="space-y-4">
                <x-textarea wire:model="body" rows="5" placeholder="Ej.: prefiere horario de la mañana; pidió factura a nombre de su empresa…" aria-label="Nota" />
                <x-checkbox wire:model="pinned" label="Fijar arriba" />
                <x-button type="submit" class="w-full" wire:loading.attr="disabled">Guardar nota</x-button>
            </form>
        </x-card>
    @endcan

    <div @class(['space-y-3', 'lg:col-span-2' => true])>
        @forelse ($notes as $note)
            <article wire:key="note-{{ $note->id }}" @class([
                'rounded-xl bg-surface p-5 ring-1',
                'ring-brand-200 bg-brand-50/40' => $note->is_pinned,
                'ring-steel-200' => ! $note->is_pinned,
            ])>
                <header class="flex items-start justify-between gap-3">
                    <p class="text-xs text-steel-700">
                        <span class="font-semibold text-ink-950">{{ $note->author?->name ?? 'Usuario eliminado' }}</span>
                        · <x-datetime :value="$note->created_at" />
                        @if ($note->updated_at?->gt($note->created_at)) · editada @endif
                        @if ($note->is_pinned)<x-badge color="brand" class="ml-1">Fijada</x-badge>@endif
                    </p>
                    <div class="flex shrink-0 gap-1">
                        @can('pin', $note)
                            <x-button variant="ghost" size="sm" wire:click="togglePin({{ $note->id }})">{{ $note->is_pinned ? 'Desfijar' : 'Fijar' }}</x-button>
                        @endcan
                        @can('update', $note)
                            <x-button variant="ghost" size="sm" wire:click="edit({{ $note->id }})">Editar</x-button>
                        @endcan
                        @can('delete', $note)
                            <x-button variant="ghost" size="sm" class="text-danger-700" wire:click="delete({{ $note->id }})" wire:confirm="¿Eliminar esta nota?">Eliminar</x-button>
                        @endcan
                    </div>
                </header>
                @if ($editingId === $note->id)
                    <form wire:submit="saveEdit" class="mt-3 space-y-3">
                        <x-textarea wire:model="editingBody" rows="4" aria-label="Editar nota" />
                        <div class="flex justify-end gap-2">
                            <x-button variant="ghost" size="sm" wire:click="cancelEdit">Cancelar</x-button>
                            <x-button type="submit" size="sm">Guardar</x-button>
                        </div>
                    </form>
                @else
                    <p class="mt-3 whitespace-pre-line text-sm text-ink-950">{{ $note->body }}</p>
                @endif
            </article>
        @empty
            <x-card><x-empty-state icon="clipboard" title="Sin notas" description="Las notas del equipo sobre este cliente aparecerán aquí." /></x-card>
        @endforelse
    </div>
</div>
