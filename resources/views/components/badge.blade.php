@props(['color' => 'neutral', 'dot' => false])
@php
    $colors = [
        'neutral' => 'bg-steel-100 text-steel-700 ring-steel-200',
        'brand' => 'bg-brand-50 text-brand-800 ring-brand-200',
        'success' => 'bg-success-50 text-success-700 ring-green-200',
        'warning' => 'bg-warning-50 text-warning-700 ring-amber-200',
        'danger' => 'bg-danger-50 text-danger-700 ring-red-200',
        'info' => 'bg-info-50 text-info-700 ring-blue-200',
        'dark' => 'bg-ink-950 text-white ring-ink-950',
    ];
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset '.($colors[$color] ?? $colors['neutral'])]) }}>
    @if ($dot)<span class="size-1.5 rounded-full bg-current" aria-hidden="true"></span>@endif
    {{ $slot }}
</span>
