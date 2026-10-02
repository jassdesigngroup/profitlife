@props(['title' => null, 'description' => null, 'padding' => true])
<section {{ $attributes->merge(['class' => 'rounded-xl bg-surface ring-1 ring-steel-200 shadow-sm']) }}>
    @if ($title || isset($actions))
        <header class="flex flex-wrap items-start justify-between gap-3 border-b border-steel-200 px-5 py-4 sm:px-6">
            <div>
                @if ($title)
                    <h2 class="text-base font-bold text-ink-950">{{ $title }}</h2>
                @endif
                @if ($description)
                    <p class="mt-0.5 text-sm text-steel-700">{{ $description }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex items-center gap-2">{{ $actions }}</div>
            @endisset
        </header>
    @endif
    <div @class(['px-5 py-5 sm:px-6' => $padding])>
        {{ $slot }}
    </div>
</section>
