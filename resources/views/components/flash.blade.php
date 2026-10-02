@php
    // Fortify devuelve claves de estado; aquí se traducen al español.
    $statusMessages = [
        'two-factor-authentication-enabled' => 'Escanee el código y confirme para terminar de activar la verificación.',
        'two-factor-authentication-confirmed' => 'Verificación en dos pasos activada.',
        'two-factor-authentication-disabled' => 'Verificación en dos pasos desactivada.',
        'recovery-codes-generated' => 'Se generaron nuevos códigos de recuperación.',
        'password-updated' => null, // se muestra en la tarjeta de contraseña
    ];
    $status = session('status');
    $statusText = is_string($status) && array_key_exists($status, $statusMessages) ? $statusMessages[$status] : $status;
@endphp
@foreach (['success' => session('success'), 'warning' => session('warning'), 'danger' => session('danger'), 'status' => $statusText] as $key => $message)
    @if ($message)
        <x-alert :type="$key === 'status' ? 'success' : $key" {{ $attributes->merge(['class' => 'mb-4']) }}>{{ $message }}</x-alert>
    @endif
@endforeach
