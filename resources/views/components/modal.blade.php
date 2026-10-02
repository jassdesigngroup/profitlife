@props(['title' => null, 'maxWidth' => 'lg'])
@php
    $widths = ['sm' => 'sm:max-w-sm', 'md' => 'sm:max-w-md', 'lg' => 'sm:max-w-lg', 'xl' => 'sm:max-w-xl', '2xl' => 'sm:max-w-2xl'];
@endphp
{{-- Se controla con wire:model sobre un booleano del componente Livewire. --}}
<div
    x-data="{ open: @entangle($attributes->wire('model')) }"
    x-show="open"
    x-on:keydown.escape.window="open = false"
    x-cloak
    class="fixed inset-0 z-50 overflow-y-auto"
    role="dialog"
    aria-modal="true"
    @if ($title) aria-label="{{ $title }}" @endif
>
    <div x-show="open" x-transition.opacity class="fixed inset-0 bg-ink-950/60" x-on:click="open = false"></div>
    <div class="relative flex min-h-full items-end justify-center p-4 sm:items-center">
        <div
            x-show="open"
            x-transition
            class="relative w-full {{ $widths[$maxWidth] ?? $widths['lg'] }} rounded-xl bg-surface shadow-2xl ring-1 ring-steel-200"
        >
            @if ($title)
                <div class="flex items-center justify-between border-b border-steel-200 px-6 py-4">
                    <h2 class="text-lg font-bold text-ink-950">{{ $title }}</h2>
                    <button type="button" class="rounded-md p-1 text-steel-500 hover:bg-steel-100 hover:text-ink-950" x-on:click="open = false">
                        <span class="sr-only">Cerrar</span>
                        <x-icon name="x" class="size-5" />
                    </button>
                </div>
            @endif
            <div class="px-6 py-5">{{ $slot }}</div>
            @isset($footer)
                <div class="flex flex-col-reverse gap-2 rounded-b-xl border-t border-steel-200 bg-canvas px-6 py-4 sm:flex-row sm:justify-end">
                    {{ $footer }}
                </div>
            @endisset
        </div>
    </div>
</div>
