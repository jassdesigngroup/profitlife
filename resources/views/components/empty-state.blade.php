@props(['icon' => 'squares', 'title', 'description' => null])
<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center px-6 py-14 text-center']) }}>
    <div class="flex size-12 items-center justify-center rounded-full bg-brand-50 text-brand-700">
        <x-icon :name="$icon" class="size-6" />
    </div>
    <h3 class="mt-4 text-base font-bold text-ink-950">{{ $title }}</h3>
    @if ($description)
        <p class="mt-1 max-w-sm text-sm text-steel-700">{{ $description }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="mt-5">{{ $slot }}</div>
    @endif
</div>
