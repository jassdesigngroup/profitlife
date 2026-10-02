<div>
    <a href="{{ $editing ? route('admin.locations.show', $locationId) : route('admin.locations.index') }}" wire:navigate class="mb-4 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-700 hover:underline">
        <x-icon name="arrow-left" class="size-4" /> Volver
    </a>
    <x-page-header eyebrow="Sedes" :title="$editing ? 'Editar sede' : 'Nueva sede'" />

    <form wire:submit="save" class="space-y-6">
        <x-card title="Identificación" description="El código corto se usa en reportes.">
            <div class="grid gap-5 sm:grid-cols-6">
                <x-input label="Nombre" wire:model.blur="name" required class="sm:col-span-3" />
                <x-input label="Código" wire:model="code" maxlength="10" required class="sm:col-span-1" />
                <x-input label="Identificador (URL)" wire:model="slug" required class="sm:col-span-2" />
            </div>
        </x-card>

        <x-card title="Ubicación y contacto">
            <div class="grid gap-5 sm:grid-cols-6">
                <x-input label="Dirección" wire:model="address_line" required class="sm:col-span-6" />
                <x-input label="Ciudad" wire:model="city" required class="sm:col-span-3" />
                <x-input label="Departamento" wire:model="department" required class="sm:col-span-3" />
                <x-input label="Teléfono" type="tel" wire:model="phone" class="sm:col-span-2" />
                <x-input label="Correo" type="email" wire:model="email" class="sm:col-span-2" />
                <x-select label="Zona horaria" wire:model="timezone" :options="$timezones" required class="sm:col-span-2" />
            </div>
        </x-card>

        <div class="flex justify-end gap-2">
            <x-button variant="secondary" :href="$editing ? route('admin.locations.show', $locationId) : route('admin.locations.index')" wire:navigate>Cancelar</x-button>
            <x-button type="submit" wire:loading.attr="disabled">{{ $editing ? 'Guardar cambios' : 'Crear sede' }}</x-button>
        </div>
    </form>
</div>
