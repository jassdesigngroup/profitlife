@props(['title', 'description' => null, 'eyebrow' => null])
<div {{ $attributes->merge(['class' => 'mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between']) }}>
    <div class="min-w-0">
        @if ($eyebrow)
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-700">{{ $eyebrow }}</p>
        @endif
        <h1 class="font-display text-3xl font-bold uppercase tracking-wide text-ink-950 sm:text-4xl">{{ $title }}</h1>
        @if ($description)
            <p class="mt-1 text-sm text-steel-700">{{ $description }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex shrink-0 flex-wrap gap-2">{{ $actions }}</div>
    @endisset
</div>
