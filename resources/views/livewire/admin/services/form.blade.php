<div>
    <a href="{{ route('admin.services.index') }}" wire:navigate class="mb-4 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-700 hover:underline">
        <x-icon name="arrow-left" class="size-4" /> Servicios
    </a>
    <x-page-header eyebrow="Administración" :title="$editing ? 'Editar servicio' : 'Nuevo servicio'" />

    <form wire:submit="save" class="grid gap-6 lg:grid-cols-[1fr_22rem]">
        <div class="space-y-6">
            <x-card title="Servicio">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-input label="Nombre" wire:model="name" maxlength="120" required class="sm:col-span-2" />
                    <x-select label="Tipo" wire:model="category" :options="$categories" />
                    <div>
                        <label for="svc-color" class="mb-1.5 block text-sm font-semibold text-ink-950">Color en la agenda</label>
                        <input id="svc-color" type="color" wire:model="color" class="h-10 w-20 cursor-pointer rounded-lg border-0 bg-surface p-1 ring-1 ring-steel-300">
                        <x-field-error name="color" />
                    </div>
                    <x-input label="Duración (minutos)" type="number" min="5" max="480" wire:model="duration_minutes" required />
                    <x-input label="Margen entre citas (minutos)" type="number" min="0" max="120" wire:model="buffer_minutes" hint="Tiempo para preparar la sala o descansar." />
                    <x-textarea label="Descripción" wire:model="description" rows="3" class="sm:col-span-2" />
                </div>
            </x-card>

            <x-card title="Sedes y precio" description="Precio general con IVA incluido. Cada sede puede tener su propio precio.">
                <div class="space-y-5">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-input label="Precio (pesos)" inputmode="numeric" wire:model="price" required />
                        <x-input label="IVA (%)" type="number" min="0" max="100" step="0.01" wire:model="tax_percent" />
                    </div>
                    <div class="divide-y divide-steel-200 rounded-lg ring-1 ring-steel-200">
                        @foreach ($locations as $location)
                            <div wire:key="lp-{{ $location->id }}" class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                                <x-checkbox wire:model="locationPrices.{{ $location->id }}.enabled" :label="'Se ofrece en '.$location->name" />
                                <input type="text" inputmode="numeric" wire:model="locationPrices.{{ $location->id }}.price" placeholder="Precio general" aria-label="Precio en {{ $location->name }}"
                                    class="w-40 rounded-lg border-0 bg-surface px-3 py-2 text-sm ring-1 ring-inset ring-steel-300 placeholder:text-steel-500 focus:ring-2 focus:ring-brand-500">
                            </div>
                        @endforeach
                    </div>
                </div>
            </x-card>

            <x-card title="Profesionales" description="Solo aparece el staff marcado como agendable en su perfil.">
                @if ($staffOptions->isEmpty())
                    <p class="text-sm text-steel-700">No hay profesionales agendables. Márquelos como “agendable” en <em>Staff</em>.</p>
                @else
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach ($staffOptions as $person)
                            <x-checkbox wire:model="staffIds" value="{{ $person->id }}" :label="$person->full_name" :description="$person->job_title" />
                        @endforeach
                    </div>
                @endif
                <x-field-error name="staffIds" />
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Opciones">
                <div class="space-y-4">
                    <x-checkbox wire:model="requires_room" label="Requiere consultorio" description="Se asigna un consultorio libre de la sede (tipo de sala “Consultorio”)." />
                    <x-checkbox wire:model="is_clinical" label="Servicio clínico" description="Activa las reglas de privacidad de la historia clínica (Fase 7)." />
                    <x-checkbox wire:model="is_bookable_online" label="Reservable en línea" description="Para el portal del cliente (más adelante)." />
                    <x-checkbox wire:model="is_active" label="Activo" />
                </div>
            </x-card>
            <div class="flex flex-col gap-2">
                <x-button type="submit" size="lg" icon="check">Guardar servicio</x-button>
                <x-button variant="secondary" :href="route('admin.services.index')" wire:navigate>Cancelar</x-button>
            </div>
        </div>
    </form>
</div>
