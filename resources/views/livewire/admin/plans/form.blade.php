<div>
    <a href="{{ route('admin.plans.index') }}" wire:navigate class="mb-4 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-700 hover:underline">
        <x-icon name="arrow-left" class="size-4" /> Planes
    </a>
    <x-page-header eyebrow="Planes" :title="$editing ? 'Editar plan' : 'Nuevo plan'" :description="$editing ? 'Los cambios de precio solo aplican a las ventas futuras.' : null" />

    <form wire:submit="save" class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card title="Plan">
                <div class="grid gap-5 sm:grid-cols-6">
                    <x-input label="Nombre" wire:model="name" required class="sm:col-span-4" />
                    <x-input label="Orden" type="number" min="0" wire:model="sort_order" class="sm:col-span-2" />
                    <x-input label="Duración" type="number" min="1" wire:model="duration_count" required class="sm:col-span-2" />
                    <x-select label="Unidad" wire:model="duration_unit" :options="$units" required class="sm:col-span-2" />
                    <x-input label="Días máx. de congelación" type="number" min="0" wire:model="max_freeze_days" hint="0 = no se puede congelar." class="sm:col-span-2" />
                    <x-textarea label="Descripción" wire:model="description" rows="2" class="sm:col-span-6" />
                    <x-textarea label="Beneficios (uno por línea)" wire:model="benefits" rows="3" class="sm:col-span-6" />
                </div>
            </x-card>

            <x-card title="Precio" description="Valores en pesos, con IVA incluido.">
                <div class="grid gap-5 sm:grid-cols-3">
                    <x-input label="Precio del periodo" inputmode="numeric" wire:model="price" placeholder="150.000" required />
                    <x-input label="Matrícula" inputmode="numeric" wire:model="enrollment_fee" hint="Solo en la primera membresía del cliente." />
                    <x-input label="IVA (%)" type="number" min="0" max="100" step="0.01" wire:model="tax_percent" hint="Confírmelo con su contador." />
                </div>
            </x-card>

            <x-card title="Acceso">
                <div class="space-y-5">
                    <x-checkbox wire:model="includes_gym_access" label="Incluye acceso al gimnasio" description="Desmárquelo para paquetes de sesiones (p. ej. 10 fisioterapias): el kiosco no dará ingreso con este plan." />
                    <x-checkbox wire:model.live="limit_visits" label="Limitar el número de ingresos" description="Por ejemplo, 12 ingresos al mes. El control se aplica en el check-in." />
                    @if ($limit_visits)
                        <div class="grid gap-5 sm:grid-cols-2">
                            <x-input label="Ingresos" type="number" min="1" wire:model="visit_limit_count" />
                            <x-select label="Periodo" wire:model="visit_limit_period" :options="$periods" />
                        </div>
                    @endif
                    <x-select label="Sedes donde es válido" wire:model.live="access_scope" :options="$scopes" />
                    @if ($access_scope === 'selected_locations')
                        <div class="grid gap-2 sm:grid-cols-2">
                            @foreach ($locations as $location)
                                <x-checkbox wire:model="locationIds" value="{{ $location->id }}" :label="$location->name" />
                            @endforeach
                        </div>
                        <x-field-error name="locationIds" />
                    @endif
                </div>
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Sesiones incluidas" description="Citas que el plan cubre. Vacío = ilimitadas. Se otorgan al vender o renovar y vencen con la membresía.">
                <div class="space-y-3">
                    @foreach ($sessions as $i => $row)
                        <div wire:key="ps-{{ $i }}" class="space-y-2 rounded-lg bg-canvas p-3 ring-1 ring-steel-200">
                            <x-select label="Servicio" wire:model="sessions.{{ $i }}.service_id" :options="$services" placeholder="Seleccione" />
                            <div class="grid grid-cols-2 gap-2">
                                <x-input label="Sesiones" type="number" min="1" wire:model="sessions.{{ $i }}.sessions" placeholder="Ilimitadas" />
                                <x-select label="Cada" wire:model="sessions.{{ $i }}.period" :options="$sessionPeriods" />
                            </div>
                            <x-button variant="ghost" size="sm" class="text-danger-700" wire:click="removeSession({{ $i }})">Quitar</x-button>
                        </div>
                    @endforeach
                    @if (count($services) > 0)
                        <x-button variant="secondary" size="sm" icon="plus" wire:click="addSession">Agregar servicio</x-button>
                    @else
                        <p class="text-sm text-steel-700">Cree primero los servicios en <em>Servicios</em>.</p>
                    @endif
                </div>
            </x-card>
            <x-card title="Venta">
                <div class="space-y-4">
                    <x-checkbox wire:model="auto_renews" label="Renovación automática" description="Al vencer se crea el periodo siguiente con su comprobante pendiente." />
                    <x-checkbox wire:model="is_active" label="En venta" />
                </div>
            </x-card>
            <div class="flex flex-col gap-2">
                <x-button type="submit" size="lg" icon="check">Guardar plan</x-button>
                <x-button variant="secondary" :href="route('admin.plans.index')" wire:navigate>Cancelar</x-button>
            </div>
        </div>
    </form>
</div>
