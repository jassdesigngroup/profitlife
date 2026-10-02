@props(['value', 'format' => 'd/m/Y H:i'])
{{-- Las fechas se guardan en UTC y se muestran en la zona horaria configurada. --}}
@if ($value)
    <time datetime="{{ $value->toIso8601String() }}" {{ $attributes }}>{{ $value->setTimezone(app(\App\Domain\Settings\Services\Settings::class)->displayTimezone())->format($format) }}</time>
@else
    <span class="text-steel-700">—</span>
@endif
