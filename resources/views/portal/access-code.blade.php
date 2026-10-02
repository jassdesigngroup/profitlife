<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>Código de acceso · {{ app(\App\Domain\Settings\Services\Settings::class)->brandName() }}</title>
    @fonts
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-full bg-ink-950 text-white">
    <main class="mx-auto flex min-h-screen max-w-md flex-col items-center justify-center px-6 py-12 text-center">
        <x-brand dark />
        @if ($qr)
            <h1 class="mt-10 font-display text-4xl font-bold uppercase">Hola, {{ $firstName }}</h1>
            <p class="mt-2 text-steel-300">Muestra este código en el kiosco de la entrada para registrar tu ingreso.</p>
            <div class="mt-8 rounded-2xl bg-white p-4">{!! $qr !!}</div>
            <p class="mt-4 font-display text-2xl font-bold tracking-widest text-brand-500">{{ $memberNumber }}</p>
            <p class="mt-6 text-sm text-steel-300">Guárdalo con una captura de pantalla. Es personal: no lo compartas.</p>
        @else
            <h1 class="mt-10 font-display text-4xl font-bold uppercase">Código no disponible</h1>
            <p class="mt-2 text-steel-300">Este código ya no es válido. Pide uno nuevo en recepción.</p>
        @endif
    </main>
</body>
</html>
