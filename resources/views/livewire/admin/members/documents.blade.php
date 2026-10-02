<div>
    <x-card title="Documentos" :description="$canClinical ? 'Incluye documentos clínicos (solo visibles para personal clínico).' : 'Documentos administrativos del cliente.'" :padding="false">
        @can('update', $member)
            <x-slot:actions><x-button size="sm" icon="plus" wire:click="create">Subir documento</x-button></x-slot:actions>
        @endcan

        @if ($documents->isEmpty())
            <x-empty-state icon="squares" title="Sin documentos" description="Suba contratos, identificaciones u otros soportes. Se guardan de forma privada." />
        @else
            <x-table>
                <x-slot:head>
                    <th scope="col" class="px-6 py-3">Documento</th>
                    <th scope="col" class="px-4 py-3">Categoría</th>
                    <th scope="col" class="px-4 py-3">Subido</th>
                    <th scope="col" class="px-6 py-3"><span class="sr-only">Acciones</span></th>
                </x-slot:head>
                @foreach ($documents as $document)
                    <tr wire:key="doc-{{ $document->uuid }}" class="hover:bg-canvas">
                        <td class="px-6 py-4">
                            <p class="font-semibold text-ink-950">{{ $document->title }}</p>
                            <p class="text-xs text-steel-700">{{ $document->original_name }} · {{ $document->humanSize() }}</p>
                        </td>
                        <td class="px-4 py-4">
                            <x-badge :color="$document->isClinical() ? 'danger' : 'neutral'">{{ $document->category->label() }}</x-badge>
                        </td>
                        <td class="px-4 py-4 text-steel-700">
                            <x-datetime :value="$document->created_at" format="d/m/Y" /><br>
                            <span class="text-xs">{{ $document->uploader?->name }}</span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex justify-end gap-1">
                                <x-button variant="ghost" size="sm" :href="route('admin.documents.download', $document)">Descargar</x-button>
                                @can('delete', $document)
                                    @if ($document->documentable_type !== 'consent')
                                        <x-button variant="ghost" size="sm" class="text-danger-700" wire:click="delete('{{ $document->uuid }}')" wire:confirm="¿Eliminar {{ $document->title }}?">Eliminar</x-button>
                                    @endif
                                @endcan
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>

    <x-modal wire:model="showForm" title="Subir documento" max-width="md">
        <form wire:submit="save" id="document-form" class="space-y-4">
            <x-input label="Título" wire:model="title" maxlength="150" required />
            <x-select label="Categoría" wire:model="category" :options="$categories" placeholder="Seleccione" required />
            <div>
                <label for="document-file" class="mb-1.5 block text-sm font-semibold text-ink-950">Archivo <span class="text-brand-700">*</span></label>
                <input id="document-file" type="file" wire:model="file" accept=".pdf,.jpg,.jpeg,.png,.webp"
                    class="block w-full text-sm text-steel-700 file:mr-3 file:rounded-lg file:border-0 file:bg-ink-950 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-ink-800">
                <p class="mt-1.5 text-xs text-steel-700">PDF o imagen, máximo 10 MB.</p>
                <div wire:loading wire:target="file" class="mt-1 text-xs font-semibold text-brand-700">Cargando archivo…</div>
                <x-field-error name="file" />
            </div>
        </form>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="open = false">Cancelar</x-button>
            <x-button type="submit" form="document-form" wire:loading.attr="disabled">Guardar</x-button>
        </x-slot:footer>
    </x-modal>
</div>
