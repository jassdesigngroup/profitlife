<div>
    <a href="{{ route('admin.locations.index') }}" wire:navigate class="mb-4 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-700 hover:underline">
        <x-icon name="arrow-left" class="size-4" /> Sedes
    </a>

    <x-page-header :eyebrow="$location->code" :title="$location->name" :description="$location->address_line.' · '.$location->city.', '.$location->department">
        <x-slot:actions>
            <x-badge :color="$location->is_active ? 'success' : 'neutral'" dot class="self-center">{{ $location->is_active ? 'Activa' : 'Inactiva' }}</x-badge>
            @can('update', $location)
                <x-button variant="secondary" icon="pencil" :href="route('admin.locations.edit', $location)" wire:navigate>Editar</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl bg-surface p-4 ring-1 ring-steel-200">
            <p class="text-xs font-bold uppercase tracking-wider text-steel-700">Contacto</p>
            <p class="mt-1 text-sm font-semibold text-ink-950">{{ $location->phone ?: 'Sin teléfono' }}</p>
            <p class="truncate text-sm text-steel-700">{{ $location->email ?: 'Sin correo' }}</p>
        </div>
        <div class="rounded-xl bg-surface p-4 ring-1 ring-steel-200">
            <p class="text-xs font-bold uppercase tracking-wider text-steel-700">Salas</p>
            <p class="mt-1 font-display text-3xl font-bold text-ink-950">{{ $location->rooms_count }}</p>
        </div>
        <div class="rounded-xl bg-surface p-4 ring-1 ring-steel-200">
            <p class="text-xs font-bold uppercase tracking-wider text-steel-700">Staff asignado</p>
            <p class="mt-1 font-display text-3xl font-bold text-ink-950">{{ $location->staff_count }}</p>
        </div>
    </div>

    <nav class="mb-5 flex gap-1 overflow-x-auto border-b border-steel-200" aria-label="Pestañas">
        @foreach (['hours' => ['Horarios', 'clock'], 'closures' => ['Cierres y festivos', 'calendar'], 'rooms' => ['Salas', 'squares']] as $key => [$label, $icon])
            <button type="button" wire:click="$set('tab', '{{ $key }}')" @class([
                '-mb-px flex items-center gap-2 whitespace-nowrap border-b-2 px-4 py-3 text-sm font-semibold',
                'border-brand-500 text-ink-950' => $tab === $key,
                'border-transparent text-steel-700 hover:text-ink-950' => $tab !== $key,
            ]) @if ($tab === $key) aria-current="page" @endif>
                <x-icon :name="$icon" @class(['size-4', 'text-brand-700' => $tab === $key]) /> {{ $label }}
            </button>
        @endforeach
    </nav>

    @if ($tab === 'hours')
        <livewire:admin.locations.location-hours :location-id="$location->id" :key="'hours-'.$location->id" />
    @elseif ($tab === 'closures')
        <livewire:admin.locations.location-closures :location-id="$location->id" :key="'closures-'.$location->id" />
    @else
        <livewire:admin.locations.location-rooms :location-id="$location->id" :key="'rooms-'.$location->id" />
    @endif
</div>
