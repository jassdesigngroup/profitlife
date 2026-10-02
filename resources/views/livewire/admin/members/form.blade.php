<div>
    <a href="{{ $editing ? route('admin.members.show', $memberId) : route('admin.members.index') }}" wire:navigate class="mb-4 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-700 hover:underline">
        <x-icon name="arrow-left" class="size-4" /> Volver
    </a>
    <x-page-header eyebrow="Clientes" :title="$editing ? 'Editar cliente' : 'Nuevo cliente'" />

    <form wire:submit="save" class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card title="Datos personales">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-input label="Nombres" wire:model="first_name" required />
                    <x-input label="Apellidos" wire:model="last_name" required />
                    <x-select label="Tipo de documento" wire:model="document_type" :options="$documentTypes" placeholder="Sin documento" />
                    <x-input label="Número de documento" wire:model="document_number" />
                    <x-input label="Fecha de nacimiento" type="date" wire:model.live.debounce.500ms="birth_date" />
                    <x-select label="Género" wire:model="gender" :options="$genders" placeholder="Sin especificar" />
                </div>

                @if ($this->age !== null && $this->age < \App\Domain\Members\Models\Member::ADULT_AGE)
                    <x-alert type="warning" title="Cliente menor de edad ({{ $this->age }} años)" class="mt-5">
                        La Ley 1581 de 2012 exige la autorización de su representante legal para tratar sus datos.
                        Registre el consentimiento de tratamiento de datos firmado por el acudiente.
                    </x-alert>
                @endif
            </x-card>

            <x-card title="Contacto">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-input label="Teléfono celular" type="tel" wire:model="phone" hint="Se usará para el check-in por teléfono." />
                    <x-input label="Correo electrónico" type="email" wire:model="email" />
                    <x-input label="Dirección" wire:model="address_line" class="sm:col-span-2" />
                    <x-input label="Ciudad" wire:model="city" />
                    <x-input label="Departamento" wire:model="department" />
                </div>
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Sede y alta">
                <div class="space-y-5">
                    <x-select label="Sede principal" wire:model="home_location_id" :options="$this->locations->pluck('name', 'id')->all()" placeholder="Seleccione una sede" required />
                    <x-input label="Fecha de ingreso" type="date" wire:model="joined_on" required />
                </div>
            </x-card>

            <div class="flex flex-col gap-2">
                <x-button type="submit" size="lg" icon="check" wire:loading.attr="disabled">{{ $editing ? 'Guardar cambios' : 'Registrar cliente' }}</x-button>
                <x-button variant="secondary" :href="$editing ? route('admin.members.show', $memberId) : route('admin.members.index')" wire:navigate>Cancelar</x-button>
            </div>
        </div>
    </form>
</div>
