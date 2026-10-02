<div>
    <a href="{{ route('admin.consents.index') }}" wire:navigate class="mb-4 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-700 hover:underline">
        <x-icon name="arrow-left" class="size-4" /> Consentimientos
    </a>
    <x-page-header eyebrow="Consentimientos" title="Publicar versión" :description="$nextVersion ? 'Se publicará como versión '.$nextVersion.' y reemplazará a la versión activa.' : null" />

    <form wire:submit="save" class="max-w-3xl space-y-6">
        <x-card>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-select label="Tipo" wire:model.live="type" :options="$types" placeholder="Seleccione" required />
                <x-input label="Título" wire:model="title" maxlength="150" required />
                <x-textarea label="Texto" wire:model="body" rows="16" required class="sm:col-span-2"
                    hint="Texto plano. Es lo que el cliente lee y acepta; una vez publicado no se puede editar." />
            </div>
        </x-card>
        <div class="flex justify-end gap-2">
            <x-button variant="secondary" :href="route('admin.consents.index')" wire:navigate>Cancelar</x-button>
            <x-button type="submit" icon="check" wire:confirm="¿Publicar esta versión? No se podrá editar después.">Publicar</x-button>
        </div>
    </form>
</div>
