<div>
    <a href="{{ route('admin.staff.index') }}" wire:navigate class="mb-4 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-700 hover:underline">
        <x-icon name="arrow-left" class="size-4" /> Staff
    </a>
    <x-page-header eyebrow="Equipo" :title="$editing ? 'Editar staff' : 'Nuevo staff'"
        :description="$editing ? null : 'La persona recibirá un correo con un enlace para definir su contraseña.'" />

    <form wire:submit="save" class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card title="Datos personales">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-input label="Nombres" wire:model="first_name" required />
                    <x-input label="Apellidos" wire:model="last_name" required />
                    <x-input label="Correo electrónico" type="email" wire:model="email" required class="sm:col-span-2"
                        :hint="$editing ? 'Si lo cambia, el enlace de invitación pendiente dejará de funcionar.' : 'Es el usuario de acceso.'" />
                    <x-select label="Tipo de documento" wire:model="document_type" :options="$documentTypes" placeholder="Sin documento" />
                    <x-input label="Número de documento" wire:model="document_number" />
                    <x-input label="Teléfono" type="tel" wire:model="phone" />
                </div>
            </x-card>

            <x-card title="Datos laborales">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-input label="Cargo" wire:model="job_title" />
                    <x-input label="Fecha de ingreso" type="date" wire:model="hired_on" />
                    <x-input label="Tarjeta profesional" wire:model="professional_license" hint="Obligatoria para fisioterapeutas." />
                    <div>
                        <label for="calendar-color" class="mb-1.5 block text-sm font-semibold text-ink-950">Color en la agenda</label>
                        <div class="flex items-center gap-3">
                            <input id="calendar-color" type="color" wire:model="calendar_color" class="h-10 w-14 cursor-pointer rounded-lg border-0 bg-surface p-1 ring-1 ring-inset ring-steel-300">
                            <span class="font-mono text-sm text-steel-700">{{ $calendar_color }}</span>
                        </div>
                        <x-field-error name="calendar_color" />
                    </div>
                    <x-checkbox wire:model="is_bookable" label="Atiende citas" description="Aparecerá como profesional al agendar." class="sm:col-span-2" />
                </div>
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Roles" description="Definen qué puede hacer en el panel.">
                @if ($this->canManageRoles)
                    <div class="space-y-3">
                        @foreach ($this->assignableRoles as $role)
                            <x-checkbox wire:model="roles" value="{{ $role->value }}" :label="$role->label()"
                                :description="$role->requiresTwoFactor() ? 'Exige verificación en dos pasos.' : null" />
                        @endforeach
                    </div>
                    <x-field-error name="roles" />
                    <x-field-error name="roles.*" />
                @else
                    <p class="text-sm text-steel-700">No tiene permiso para cambiar los roles de esta persona.</p>
                @endif
            </x-card>

            <x-card title="Sedes" description="Solo verá la información de estas sedes.">
                <div class="space-y-3">
                    @forelse ($this->assignableLocations as $location)
                        <div class="flex items-center justify-between gap-2">
                            <x-checkbox wire:model.live="locationIds" value="{{ $location->id }}" :label="$location->name.($location->is_active ? '' : ' (inactiva)')" />
                            @if (in_array((string) $location->id, array_map('strval', $locationIds), true))
                                <label class="flex items-center gap-1.5 text-xs font-semibold text-steel-700">
                                    <input type="radio" wire:model="primaryLocationId" value="{{ $location->id }}" class="accent-brand-500"> Principal
                                </label>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-steel-700">No tiene sedes disponibles.</p>
                    @endforelse
                </div>
                @if ($outsideLocations !== [])
                    <p class="mt-4 rounded-lg bg-canvas p-3 text-xs text-steel-700">
                        También asignado a: <span class="font-semibold text-ink-950">{{ implode(', ', $outsideLocations) }}</span> (fuera de su alcance, no se modifican).
                    </p>
                @endif
                <x-field-error name="locationIds" />
                <x-field-error name="locationIds.*" />
            </x-card>

            <div class="flex flex-col gap-2">
                <x-button type="submit" size="lg" wire:loading.attr="disabled" :icon="$editing ? 'check' : 'paper-plane'">
                    {{ $editing ? 'Guardar cambios' : 'Crear e invitar' }}
                </x-button>
                <x-button variant="secondary" :href="route('admin.staff.index')" wire:navigate>Cancelar</x-button>
            </div>
        </div>
    </form>
</div>
