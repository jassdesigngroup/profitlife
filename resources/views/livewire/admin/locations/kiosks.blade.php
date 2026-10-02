<div>
    <x-card title="Kioscos de check-in" description="Tablets o celulares fijos en la entrada donde los clientes registran su ingreso." :padding="false">
        @can('create', [\App\Domain\CheckIns\Models\KioskDevice::class, $location])
            <x-slot:actions>
                <x-button size="sm" icon="plus" wire:click="create">Nuevo kiosco</x-button>
            </x-slot:actions>
        @endcan

        @if ($devices->isEmpty())
            <x-empty-state icon="tablet" title="Sin kioscos" description="Cree un kiosco y abra su enlace de vinculación en la tablet de la entrada." />
        @else
            <x-table>
                <x-slot:head>
                    <th scope="col" class="px-6 py-3">Kiosco</th>
                    <th scope="col" class="px-4 py-3">Vinculación</th>
                    <th scope="col" class="px-4 py-3">Última conexión</th>
                    <th scope="col" class="px-4 py-3">Estado</th>
                    <th scope="col" class="px-6 py-3"><span class="sr-only">Acciones</span></th>
                </x-slot:head>
                @foreach ($devices as $device)
                    <tr wire:key="kiosk-{{ $device->id }}" class="hover:bg-canvas">
                        <td class="px-6 py-4 font-semibold text-ink-950">{{ $device->name }}</td>
                        <td class="px-4 py-4">
                            <x-badge :color="$device->tokens_count > 0 ? 'info' : 'neutral'">{{ $device->tokens_count > 0 ? 'Vinculado' : 'Sin vincular' }}</x-badge>
                        </td>
                        <td class="px-4 py-4 text-steel-700">
                            @if ($device->last_seen_at)
                                <x-datetime :value="$device->last_seen_at" />
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-4"><x-badge :color="$device->is_active ? 'success' : 'neutral'" dot>{{ $device->is_active ? 'Activo' : 'Inactivo' }}</x-badge></td>
                        <td class="px-6 py-4">
                            @can('update', $device)
                                <div class="flex justify-end gap-1">
                                    @if ($device->tokens_count > 0)
                                        <x-button variant="ghost" size="sm" wire:click="pair({{ $device->id }})" wire:confirm="¿Generar un enlace nuevo? El dispositivo vinculado actualmente dejará de funcionar.">Nuevo enlace</x-button>
                                        <x-button variant="ghost" size="sm" wire:click="unpair({{ $device->id }})" wire:confirm="¿Desvincular {{ $device->name }}?">Desvincular</x-button>
                                    @else
                                        <x-button variant="ghost" size="sm" wire:click="pair({{ $device->id }})">Vincular</x-button>
                                    @endif
                                    <x-button variant="ghost" size="sm" wire:click="edit({{ $device->id }})">Editar</x-button>
                                    <x-button variant="ghost" size="sm" class="text-danger-700" wire:click="delete({{ $device->id }})" wire:confirm="¿Eliminar el kiosco {{ $device->name }}?">Eliminar</x-button>
                                </div>
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>

    <x-modal wire:model="showForm" :title="$deviceId ? 'Editar kiosco' : 'Nuevo kiosco'" max-width="md">
        <form wire:submit="save" id="kiosk-form" class="space-y-4">
            <x-input label="Nombre" wire:model="name" maxlength="100" placeholder="Tablet entrada principal" required />
            <x-checkbox wire:model="isActive" label="Kiosco activo" description="Un kiosco inactivo no puede registrar ingresos." />
        </form>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="open = false">Cancelar</x-button>
            <x-button type="submit" form="kiosk-form" wire:loading.attr="disabled">Guardar</x-button>
        </x-slot:footer>
    </x-modal>

    <x-modal wire:model="showPair" title="Vincular kiosco" max-width="md">
        @if ($pairingQr)
            <div class="space-y-4 text-sm text-steel-700">
                <p>Abra este enlace en la tablet (escaneando el código con su cámara o copiándolo). Se muestra <strong class="text-ink-950">una sola vez</strong>: si lo pierde, genere uno nuevo.</p>
                <div class="mx-auto w-fit rounded-lg bg-white p-2 ring-1 ring-steel-200">{!! $pairingQr !!}</div>
                <div x-data="{ copied: false }" class="flex gap-2">
                    <input type="text" readonly x-ref="pairUrl" value="{{ $pairingUrl }}" aria-label="Enlace de vinculación" class="min-w-0 flex-1 rounded-lg border-0 bg-canvas px-3 py-2 text-xs ring-1 ring-steel-300" x-on:focus="$el.select()">
                    <x-button variant="secondary" size="sm" x-on:click="navigator.clipboard?.writeText($refs.pairUrl.value); copied = true" x-text="copied ? 'Copiado' : 'Copiar'">Copiar</x-button>
                </div>
            </div>
        @endif
        <x-slot:footer>
            <x-button wire:click="closePair">Listo</x-button>
        </x-slot:footer>
    </x-modal>
</div>
