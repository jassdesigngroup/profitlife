@props(['label', 'description' => null])
<label {{ $attributes->only('class')->merge(['class' => 'flex items-start gap-3 cursor-pointer']) }}>
    <input type="checkbox" {{ $attributes->except('class')->merge(['class' => 'mt-0.5 size-4 rounded border-steel-500 text-brand-500 accent-brand-500 focus:ring-brand-500']) }} />
    <span class="text-sm">
        <span class="font-semibold text-ink-950">{{ $label }}</span>
        @if ($description)
            <span class="block text-steel-700">{{ $description }}</span>
        @endif
    </span>
</label>
