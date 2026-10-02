<div>
    <a href="{{ route('admin.roles.index') }}" wire:navigate class="mb-4 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-700 hover:underline">
        <x-icon name="arrow-left" class="size-4" /> Roles
    </a>
    <x-page-header eyebrow="Rol" :title="$label" :description="$canEdit ? 'Marque los permisos de este rol. Los cambios quedan en la auditoría.' : 'Vista de solo lectura. Solo Super Admin cambia permisos.'">
        @if ($canEdit)
            <x-slot:actions>
                <x-button wire:click="save" icon="check" wire:loading.attr="disabled">Guardar permisos</x-button>
            </x-slot:actions>
        @endif
    </x-page-header>

    @if ($role->name === \App\Domain\Identity\Enums\RoleName::SuperAdmin->value)
        <x-alert type="info" class="mb-6">Super Admin tiene siempre todos los permisos y no se puede editar.</x-alert>
    @endif

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($groups as $resource => $items)
            <x-card :title="\App\Domain\Identity\Enums\Permission::resourceLabel($resource)">
                @if ($items[0]->isClinical())
                    <x-slot:actions><x-badge color="danger">Clínico</x-badge></x-slot:actions>
                @endif
                <div class="space-y-2.5">
                    @foreach ($items as $permission)
                        <label class="flex items-start gap-3 text-sm">
                            <input type="checkbox" wire:model="permissions" value="{{ $permission->value }}" @disabled(! $canEdit)
                                class="mt-0.5 size-4 rounded accent-brand-500 disabled:opacity-60">
                            <span>
                                <span class="font-semibold text-ink-950">{{ $permission->label() }}</span>
                                <span class="block font-mono text-xs text-steel-700">{{ $permission->value }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </x-card>
        @endforeach
    </div>
</div>
