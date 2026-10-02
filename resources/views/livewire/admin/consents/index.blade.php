<div>
    <x-page-header eyebrow="Administración" title="Consentimientos" description="Textos legales versionados. Publicar una versión nueva no altera lo que los clientes ya aceptaron.">
        <x-slot:actions>
            <x-button :href="route('admin.consents.create')" icon="plus" wire:navigate>Nueva plantilla</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-alert type="info" class="mb-6">Haga revisar los textos por su asesor legal antes de publicarlos.</x-alert>

    <div class="grid gap-6 lg:grid-cols-2">
        @foreach ($types as $type)
            @php $versions = $templates->get($type->value, collect()); @endphp
            <x-card :title="$type->label()" :padding="false">
                <x-slot:actions>
                    <x-button variant="secondary" size="sm" :href="route('admin.consents.create', ['type' => $type->value])" wire:navigate>
                        {{ $versions->isEmpty() ? 'Crear' : 'Nueva versión' }}
                    </x-button>
                </x-slot:actions>
                @if ($versions->isEmpty())
                    <p class="px-6 py-5 text-sm text-steel-700">Sin plantilla publicada.</p>
                @else
                    <ul class="divide-y divide-steel-200">
                        @foreach ($versions as $template)
                            <li wire:key="tpl-{{ $template->id }}" class="flex items-center gap-3 px-6 py-3">
                                <span class="font-mono text-sm font-bold text-ink-950">v{{ $template->version }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold text-ink-950">{{ $template->title }}</p>
                                    <p class="text-xs text-steel-700">{{ $template->consents_count }} aceptaciones vigentes · <x-datetime :value="$template->created_at" format="d/m/Y" /></p>
                                </div>
                                <x-badge :color="$template->is_active ? 'success' : 'neutral'" dot>{{ $template->is_active ? 'Activa' : 'Inactiva' }}</x-badge>
                                <x-button variant="ghost" size="sm" wire:click="preview({{ $template->id }})">Ver</x-button>
                                <x-button variant="ghost" size="sm" wire:click="toggle({{ $template->id }})">{{ $template->is_active ? 'Desactivar' : 'Activar' }}</x-button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        @endforeach
    </div>

    @if ($preview)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-ink-950/60 p-4 sm:items-center" wire:click.self="closePreview" role="dialog" aria-modal="true">
            <div class="w-full max-w-2xl rounded-xl bg-surface shadow-2xl">
                <div class="flex items-center justify-between border-b border-steel-200 px-6 py-4">
                    <h2 class="text-lg font-bold text-ink-950">{{ $preview->title }} · v{{ $preview->version }}</h2>
                    <button type="button" class="rounded-md p-1 text-steel-500 hover:bg-steel-100" wire:click="closePreview"><span class="sr-only">Cerrar</span><x-icon name="x" /></button>
                </div>
                <div class="max-h-[60vh] overflow-y-auto whitespace-pre-line px-6 py-5 text-sm leading-relaxed text-ink-950">{{ $preview->body }}</div>
            </div>
        </div>
    @endif
</div>
