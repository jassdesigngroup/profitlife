<div class="space-y-6">
    <x-card title="Consentimientos vigentes" description="Versión activa de cada plantilla y su estado para este cliente." :padding="false">
        @if ($templates->isEmpty())
            <x-empty-state icon="shield" title="No hay plantillas activas" description="Un administrador debe publicar las plantillas en Administración → Consentimientos." />
        @else
            <ul class="divide-y divide-steel-200">
                @foreach ($templates as $template)
                    <li wire:key="tpl-{{ $template->id }}" class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:px-6">
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-ink-950">{{ $template->title }} <span class="font-mono text-xs text-steel-700">v{{ $template->version }}</span></p>
                            <p class="text-xs text-steel-700">{{ $template->type->label() }}</p>
                        </div>
                        @switch($status[$template->id])
                            @case('valid') <x-badge color="success" dot>Aceptado</x-badge> @break
                            @case('outdated') <x-badge color="warning" dot>Versión anterior aceptada</x-badge> @break
                            @default <x-badge color="neutral" dot>Pendiente</x-badge>
                        @endswitch
                        @can('update', $member)
                            @if ($status[$template->id] !== 'valid')
                                <x-button size="sm" wire:click="capture({{ $template->id }})">Registrar</x-button>
                            @endif
                        @endcan
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>

    <x-card title="Historial" :padding="false">
        @if ($consents->isEmpty())
            <x-empty-state icon="clipboard" title="Sin consentimientos registrados" />
        @else
            <x-table>
                <x-slot:head>
                    <th scope="col" class="px-6 py-3">Consentimiento</th>
                    <th scope="col" class="px-4 py-3">Firmó</th>
                    <th scope="col" class="px-4 py-3">Método</th>
                    <th scope="col" class="px-4 py-3">Estado</th>
                    <th scope="col" class="px-6 py-3"><span class="sr-only">Acciones</span></th>
                </x-slot:head>
                @foreach ($consents as $consent)
                    <tr wire:key="consent-{{ $consent->id }}">
                        <td class="px-6 py-4">
                            <p class="font-semibold text-ink-950">{{ $consent->template->title }} <span class="font-mono text-xs text-steel-700">v{{ $consent->template->version }}</span></p>
                            <p class="text-xs text-steel-700"><x-datetime :value="$consent->accepted_at" /> · registró {{ $consent->capturer?->name ?? '—' }}</p>
                        </td>
                        <td class="px-4 py-4 text-ink-950">{{ $consent->signed_name }}</td>
                        <td class="px-4 py-4 text-steel-700">
                            {{ $consent->method->label() }}
                            @if ($consent->document)
                                <a href="{{ route('admin.documents.download', $consent->document) }}" class="ml-1 font-semibold text-brand-700 hover:underline">Ver soporte</a>
                            @endif
                        </td>
                        <td class="px-4 py-4">
                            @if ($consent->isRevoked())
                                <x-badge color="danger">Revocado <x-datetime :value="$consent->revoked_at" format="d/m/Y" /></x-badge>
                            @else
                                <x-badge color="success">Vigente</x-badge>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right">
                            @can('update', $member)
                                @unless ($consent->isRevoked())
                                    <x-button variant="ghost" size="sm" class="text-danger-700" wire:click="revoke({{ $consent->id }})"
                                        wire:confirm="¿Registrar la revocación de este consentimiento?">Revocar</x-button>
                                @endunless
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>

    <x-modal wire:model="showForm" :title="$current ? $current->title.' · v'.$current->version : 'Consentimiento'" max-width="2xl">
        @if ($current)
            <div class="max-h-72 overflow-y-auto whitespace-pre-line rounded-lg bg-canvas p-4 text-sm leading-relaxed text-ink-950 ring-1 ring-steel-200">{{ $current->body }}</div>
            <form wire:submit="save" id="consent-form" class="mt-5 grid gap-4 sm:grid-cols-2">
                <x-select label="Método" wire:model.live="method" :options="$methods" required />
                <x-input :label="$member->isMinor() ? 'Nombre del acudiente que firma' : 'Nombre de quien firma'" wire:model="signedName" required />
                @if ($method === 'digital')
                    <x-checkbox wire:model="accepted" class="sm:col-span-2" label="La persona leyó el texto en pantalla y lo acepta"
                        description="Se registran la fecha, la hora, la IP y quién lo capturó." />
                    <x-field-error name="accepted" class="sm:col-span-2" />
                @else
                    <div class="sm:col-span-2">
                        <label for="consent-scan" class="mb-1.5 block text-sm font-semibold text-ink-950">Documento firmado <span class="text-brand-700">*</span></label>
                        <input id="consent-scan" type="file" wire:model="scan" accept=".pdf,.jpg,.jpeg,.png,.webp"
                            class="block w-full text-sm text-steel-700 file:mr-3 file:rounded-lg file:border-0 file:bg-ink-950 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white">
                        <x-field-error name="scan" />
                    </div>
                @endif
                <x-field-error name="templateId" class="sm:col-span-2" />
            </form>
        @endif
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="open = false">Cancelar</x-button>
            <x-button type="submit" form="consent-form" wire:loading.attr="disabled">Registrar consentimiento</x-button>
        </x-slot:footer>
    </x-modal>
</div>
