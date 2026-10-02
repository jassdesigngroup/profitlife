@foreach (['success', 'warning', 'danger', 'status' => 'success'] as $key => $type)
    @php $sessionKey = is_int($key) ? $type : $key; @endphp
    @if (session($sessionKey))
        <x-alert :type="$type" {{ $attributes->merge(['class' => 'mb-4']) }}>{{ session($sessionKey) }}</x-alert>
    @endif
@endforeach
