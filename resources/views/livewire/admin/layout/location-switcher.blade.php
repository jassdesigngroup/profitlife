<div class="flex items-center gap-2">
    <x-icon name="map-pin" class="hidden size-5 text-steel-500 sm:block" />
    @if ($this->locations->isEmpty())
        <span class="text-sm font-semibold text-steel-700">Sin sedes asignadas</span>
    @elseif ($this->locations->count() === 1 && ! auth()->user()->canAccessAllLocations())
        <span class="text-sm font-semibold text-ink-950">{{ $this->locations->first()->name }}</span>
    @else
        <label for="location-switcher" class="sr-only">Sede</label>
        <select id="location-switcher" wire:model.live="locationId"
            class="max-w-[14rem] rounded-lg border-0 bg-steel-100 py-1.5 pl-3 pr-9 text-sm font-semibold text-ink-950 ring-1 ring-inset ring-transparent focus:ring-2 focus:ring-brand-500">
            <option value="">Todas las sedes</option>
            @foreach ($this->locations as $location)
                <option value="{{ $location->id }}">{{ $location->name }}{{ $location->is_active ? '' : ' (inactiva)' }}</option>
            @endforeach
        </select>
    @endif
</div>
