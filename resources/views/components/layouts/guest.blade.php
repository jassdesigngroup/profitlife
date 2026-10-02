@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ? $title.' · ' : '' }}{{ app(\App\Domain\Settings\Services\Settings::class)->brandName() }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-surface">
    <div class="grid min-h-full lg:grid-cols-[1.05fr_1fr]">
        {{-- Panel de marca --}}
        <aside class="relative hidden overflow-hidden bg-ink-950 lg:flex lg:flex-col lg:justify-between lg:p-12">
            <div class="pointer-events-none absolute -right-32 -top-32 size-[28rem] rounded-full bg-brand-500/20 blur-3xl" aria-hidden="true"></div>
            <div class="pointer-events-none absolute -bottom-40 -left-24 size-[26rem] rounded-full bg-brand-500/10 blur-3xl" aria-hidden="true"></div>
            <div class="pointer-events-none absolute inset-y-0 right-16 w-px rotate-12 bg-gradient-to-b from-transparent via-brand-500/60 to-transparent" aria-hidden="true"></div>
            <x-brand dark class="relative" />
            <div class="relative max-w-md">
                <p class="text-xs font-bold uppercase tracking-[0.25em] text-brand-500">Rendimiento · Fisioterapia</p>
                <p class="mt-4 font-display text-5xl font-bold uppercase leading-[0.95] tracking-wide text-white">
                    Entrena mejor.<br>Recupérate antes.<br><span class="text-brand-500">Gestiona sin fricción.</span>
                </p>
                <p class="mt-6 text-base text-steel-300">Panel de gestión de sedes, equipo y operación diaria.</p>
            </div>
            <p class="relative text-xs text-steel-300">&copy; {{ now()->year }} {{ app(\App\Domain\Settings\Services\Settings::class)->brandName() }}</p>
        </aside>

        <main class="flex flex-col justify-center px-6 py-12 sm:px-12 lg:px-20">
            <div class="mx-auto w-full max-w-sm">
                <x-brand class="mb-10 lg:hidden" />
                {{ $slot }}
            </div>
        </main>
    </div>
</body>
</html>
