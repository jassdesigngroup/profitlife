<div>
    <x-page-header eyebrow="Administración" title="Servicios" description="Lo que se agenda: fisioterapia, entrenamiento personal, valoraciones.">
        <x-slot:actions>
            @can('create', \App\Domain\Appointments\Models\Service::class)
                <x-button icon="plus" :href="route('admin.services.create')" wire:navigate>Nuevo servicio</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-card :padding="false">
        @if ($services->isEmpty())
            <x-empty-state icon="calendar" title="Sin servicios" description="Cree los servicios para poder agendar citas." />
        @else
            <x-table>
                <x-slot:head>
                    <th scope="col" class="px-6 py-3">Servicio</th>
                    <th scope="col" class="px-4 py-3">Duración</th>
                    <th scope="col" class="px-4 py-3">Precio</th>
                    <th scope="col" class="px-4 py-3 text-center">Profesionales</th>
                    <th scope="col" class="px-4 py-3 text-center">Sedes</th>
                    <th scope="col" class="px-4 py-3">Estado</th>
                    <th scope="col" class="px-6 py-3"><span class="sr-only">Acciones</span></th>
                </x-slot:head>
                @foreach ($services as $service)
                    <tr wire:key="svc-{{ $service->id }}" class="hover:bg-canvas">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-2.5">
                                <span class="size-3 shrink-0 rounded-full" style="background: {{ $service->color ?: '#828282' }}" aria-hidden="true"></span>
                                <div>
                                    <p class="font-semibold text-ink-950">{{ $service->name }}</p>
                                    <p class="text-xs text-steel-700">{{ $service->category->label() }}@if ($service->is_clinical) · clínico @endif @if ($service->requires_room) · requiere consultorio @endif</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-4 text-steel-700">{{ $service->duration_minutes }} min @if ($service->buffer_minutes) <span class="text-xs">+{{ $service->buffer_minutes }}</span> @endif</td>
                        <td class="px-4 py-4 font-semibold text-ink-950">{{ \App\Support\Money::ofCents($service->price_cents, $service->currency)->format() }}</td>
                        <td class="px-4 py-4 text-center">{{ $service->staff_count }}</td>
                        <td class="px-4 py-4 text-center">{{ $service->locations_count }}</td>
                        <td class="px-4 py-4"><x-badge :color="$service->is_active ? 'success' : 'neutral'" dot>{{ $service->is_active ? 'Activo' : 'Inactivo' }}</x-badge></td>
                        <td class="px-6 py-4 text-right">
                            @can('update', $service)
                                <x-button variant="ghost" size="sm" :href="route('admin.services.edit', $service)" wire:navigate>Editar</x-button>
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>
</div>
