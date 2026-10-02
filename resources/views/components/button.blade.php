@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'icon' => null,
    'type' => 'button',
])
@php
    // Primario: naranja de marca con texto blanco en negrita.
    $variants = [
        'primary' => 'bg-brand-500 text-white font-bold hover:bg-brand-600 active:bg-brand-700 shadow-sm shadow-brand-500/20',
        'secondary' => 'bg-surface text-ink-950 font-semibold ring-1 ring-inset ring-steel-300 hover:bg-steel-100',
        'dark' => 'bg-ink-950 text-white font-semibold hover:bg-ink-800',
        'ghost' => 'text-ink-950 font-semibold hover:bg-steel-100',
        'danger' => 'bg-danger-700 text-white font-bold hover:bg-red-800',
        'link' => 'text-brand-700 font-semibold underline-offset-4 hover:underline px-0! py-0!',
    ];
    $sizes = [
        'sm' => 'text-sm px-3 py-1.5 gap-1.5 rounded-md',
        'md' => 'text-sm px-4 py-2.5 gap-2 rounded-lg',
        'lg' => 'text-base px-5 py-3 gap-2 rounded-lg',
    ];
    $classes = 'inline-flex items-center justify-center whitespace-nowrap transition-colors duration-150 disabled:opacity-50 disabled:cursor-not-allowed '.$variants[$variant].' '.$sizes[$size];
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-icon :name="$icon" class="size-4 shrink-0" />@endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-icon :name="$icon" class="size-4 shrink-0" />@endif
        {{ $slot }}
    </button>
@endif
