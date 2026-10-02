@props([
    'label' => null,
    'name' => null,
    'options' => [],
    'placeholder' => null,
    'hint' => null,
    'required' => false,
])
@php
    $model = $attributes->wire('model')->value();
    $field = $name ?? $model;
    $id = $attributes->get('id') ?? 'f-'.str_replace(['.', '[', ']'], '-', (string) $field).'-'.\Illuminate\Support\Str::random(4);
    $hasError = $field && $errors->has($field);
@endphp
<div {{ $attributes->only('class') }}>
    @if ($label)
        <label for="{{ $id }}" class="mb-1.5 block text-sm font-semibold text-ink-950">
            {{ $label }}@if ($required)<span class="text-brand-700" aria-hidden="true"> *</span>@endif
        </label>
    @endif
    <select
        id="{{ $id }}"
        @if ($name) name="{{ $name }}" @endif
        @if ($required) required @endif
        @if ($hasError) aria-invalid="true" @endif
        {{ $attributes->except('class')->merge([
            'class' => 'block w-full rounded-lg border-0 bg-surface py-2.5 pl-3.5 pr-10 text-sm text-ink-950 ring-1 ring-inset focus:ring-2 focus:ring-inset focus:ring-brand-500 '.($hasError ? 'ring-danger-700' : 'ring-steel-300'),
        ]) }}
    >
        @if ($placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $value => $text)
            <option value="{{ $value }}">{{ $text }}</option>
        @endforeach
        {{ $slot }}
    </select>
    @if ($hint)
        <p class="mt-1.5 text-xs text-steel-700">{{ $hint }}</p>
    @endif
    @if ($field)
        <x-field-error :name="$field" />
    @endif
</div>
