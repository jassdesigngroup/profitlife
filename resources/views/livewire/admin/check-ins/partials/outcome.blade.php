@if ($outcome)
    <div @class([
        'flex items-start gap-3 rounded-xl p-4 ring-1',
        'bg-success-50 ring-success-700/30' => $outcome['accepted'] || $outcome['duplicate'],
        'bg-danger-50 ring-danger-700/30' => ! $outcome['accepted'] && ! $outcome['duplicate'],
    ]) role="status">
        <x-icon :name="$outcome['accepted'] || $outcome['duplicate'] ? 'check-circle' : 'warning'" @class([
            'mt-0.5 size-6 shrink-0',
            'text-success-700' => $outcome['accepted'] || $outcome['duplicate'],
            'text-danger-700' => ! $outcome['accepted'] && ! $outcome['duplicate'],
        ]) />
        <div class="min-w-0 flex-1">
            <p class="font-bold text-ink-950">{{ $outcome['title'] }}</p>
            @if ($outcome['reason'] && ! $outcome['duplicate'])
                <p class="text-sm font-semibold text-danger-700">{{ $outcome['reason'] }}</p>
            @endif
            @if ($outcome['warnings'])
                <ul class="mt-1 space-y-0.5 text-sm text-ink-800">
                    @foreach ($outcome['warnings'] as $warning)
                        <li>· {{ $warning }}</li>
                    @endforeach
                </ul>
            @endif
            @if ($outcome['canOverride'])
                <x-button size="sm" variant="secondary" class="mt-3" wire:click="openOverride({{ $outcome['checkInId'] }})">Autorizar ingreso</x-button>
            @endif
        </div>
        <button type="button" wire:click="dismissOutcome" class="rounded-md p-1 text-steel-500 hover:bg-white/60 hover:text-ink-950">
            <span class="sr-only">Cerrar</span><x-icon name="x" class="size-4" />
        </button>
    </div>
@endif
