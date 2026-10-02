@props(['type' => 'info', 'title' => null])
@php
    $styles = [
        'info' => ['bg-info-50 text-info-700 ring-blue-200', 'info'],
        'success' => ['bg-success-50 text-success-700 ring-green-200', 'check-circle'],
        'warning' => ['bg-warning-50 text-warning-700 ring-amber-200', 'warning'],
        'danger' => ['bg-danger-50 text-danger-700 ring-red-200', 'warning'],
    ];
    [$classes, $icon] = $styles[$type] ?? $styles['info'];
@endphp
<div {{ $attributes->merge(['class' => 'flex gap-3 rounded-lg p-4 ring-1 ring-inset '.$classes]) }} role="{{ $type === 'danger' ? 'alert' : 'status' }}">
    <x-icon :name="$icon" class="size-5 shrink-0" />
    <div class="text-sm">
        @if ($title)
            <p class="font-bold">{{ $title }}</p>
        @endif
        <div @class(['mt-1' => $title])>{{ $slot }}</div>
    </div>
</div>
