@props(['dark' => false])
@php $brand = app(\App\Domain\Settings\Services\Settings::class)->brandName(); @endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }}>
    <span class="flex size-9 items-center justify-center rounded-lg bg-brand-500 text-white shadow-md shadow-brand-500/30">
        <x-icon name="bolt" class="size-5" stroke-width="2.2" />
    </span>
    <span @class([
        'font-display text-2xl font-bold uppercase leading-none tracking-wider',
        'text-white' => $dark,
        'text-ink-950' => ! $dark,
    ])>{{ $brand }}</span>
</span>
