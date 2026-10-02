<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>Check-in · {{ app(\App\Domain\Settings\Services\Settings::class)->brandName() }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/kiosk.js'])
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="h-full select-none overflow-hidden bg-ink-950 text-white">
<div x-data="kiosk" class="relative flex h-full flex-col">
    <div class="pointer-events-none absolute -right-40 -top-40 size-[34rem] rounded-full bg-brand-500/15 blur-3xl" aria-hidden="true"></div>

    {{-- Encabezado --}}
    <header class="relative flex items-center justify-between px-6 py-5 sm:px-10">
        <x-brand dark />
        <div class="text-right">
            <p class="font-display text-3xl font-bold leading-none" x-text="clock"></p>
            <p class="mt-1 text-xs font-bold uppercase tracking-[0.2em] text-brand-500" x-text="info.location"></p>
        </div>
    </header>

    <main class="relative flex flex-1 flex-col items-center justify-center px-6 pb-8 sm:px-10">
        {{-- Cargando --}}
        <p x-show="state === 'loading'" class="text-lg text-steel-300">Conectando…</p>

        {{-- Sin conexión --}}
        <div x-show="state === 'offline'" x-cloak class="max-w-md text-center">
            <x-icon name="warning" class="mx-auto size-14 text-brand-500" />
            <p class="mt-4 font-display text-3xl font-bold uppercase">Sin conexión</p>
            <p class="mt-2 text-steel-300">Reintentando en unos segundos. Si continúa, acércate a recepción.</p>
        </div>

        {{-- Sin vincular --}}
        <div x-show="state === 'unpaired'" x-cloak class="w-full max-w-md text-center">
            <x-icon name="tablet" class="mx-auto size-14 text-brand-500" />
            <h1 class="mt-4 font-display text-4xl font-bold uppercase">Kiosco sin vincular</h1>
            <p class="mt-2 text-steel-300">En el panel, abra <strong class="text-white">Sedes → Kioscos</strong>, genere el enlace de vinculación y ábralo en este dispositivo. También puede pegar el código aquí.</p>
            <form x-on:submit.prevent="pair" class="mt-6 flex gap-2">
                <label for="pair" class="sr-only">Código de vinculación</label>
                <input id="pair" x-model="pairInput" type="password" autocomplete="off" placeholder="Código de vinculación"
                       class="min-w-0 flex-1 rounded-lg border-0 bg-white/10 px-4 py-3 text-white ring-1 ring-white/20 placeholder:text-steel-300 focus:ring-2 focus:ring-brand-500">
                <button type="submit" class="rounded-lg bg-brand-500 px-5 py-3 font-bold text-white hover:bg-brand-600">Vincular</button>
            </form>
        </div>

        {{-- Registro --}}
        <div x-show="state === 'idle' || state === 'busy'" x-cloak class="flex w-full max-w-3xl flex-col items-center">
            <h1 class="text-center font-display text-5xl font-bold uppercase tracking-wide sm:text-6xl">Registra tu <span class="text-brand-500">ingreso</span></h1>

            <div class="mt-8 grid w-full grid-cols-3 gap-3" role="tablist">
                @foreach (['qr' => ['qr-code', 'Código QR'], 'number' => ['hashtag', 'N.º de cliente'], 'phone' => ['phone', 'Celular + PIN']] as $key => [$icon, $label])
                    <button type="button" role="tab" x-on:click="setMode('{{ $key }}')" :aria-selected="mode === '{{ $key }}'"
                            :class="mode === '{{ $key }}' ? 'bg-brand-500 text-white ring-brand-500' : 'bg-white/5 text-steel-300 ring-white/15 hover:bg-white/10'"
                            class="flex flex-col items-center gap-2 rounded-xl px-3 py-4 font-bold ring-1 transition sm:flex-row sm:justify-center">
                        <x-icon :name="$icon" class="size-6" /> <span class="text-sm sm:text-base">{{ $label }}</span>
                    </button>
                @endforeach
            </div>

            {{-- QR --}}
            <div x-show="mode === 'qr'" class="mt-8 flex w-full flex-col items-center">
                <div class="relative aspect-square w-full max-w-sm overflow-hidden rounded-2xl bg-black ring-2 ring-brand-500/60">
                    <video x-ref="video" playsinline muted class="size-full -scale-x-100 object-cover"></video>
                    <div class="pointer-events-none absolute inset-10 rounded-xl border-4 border-dashed border-white/50" aria-hidden="true"></div>
                </div>
                <p x-show="cameraError" class="mt-4 text-center text-brand-500" x-text="cameraError"></p>
                <p class="mt-4 text-center text-steel-300">Acerca el código QR de tu membresía a la cámara.</p>
            </div>

            {{-- Teclado numérico --}}
            <div x-show="mode !== 'qr'" class="mt-8 w-full max-w-sm">
                <div class="rounded-xl bg-white/5 px-5 py-4 text-center ring-1 ring-white/15">
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-steel-300"
                       x-text="mode === 'number' ? 'Número de cliente' : (phoneStep === 'phone' ? 'Número de celular' : 'PIN de 4 dígitos')"></p>
                    <p class="mt-1 h-12 font-display text-5xl font-bold tracking-widest"
                       x-text="mode === 'number' ? number : (phoneStep === 'phone' ? phone : '•'.repeat(pin.length))"></p>
                </div>
                <div class="mt-4 grid grid-cols-3 gap-3">
                    @foreach (['1', '2', '3', '4', '5', '6', '7', '8', '9'] as $digit)
                        <button type="button" x-on:click="press('{{ $digit }}')" class="rounded-xl bg-white/10 py-5 font-display text-4xl font-bold hover:bg-white/15 active:bg-brand-500">{{ $digit }}</button>
                    @endforeach
                    <button type="button" x-on:click="erase()" class="flex items-center justify-center rounded-xl bg-white/5 py-5 hover:bg-white/10" aria-label="Borrar"><x-icon name="backspace" class="size-8" /></button>
                    <button type="button" x-on:click="press('0')" class="rounded-xl bg-white/10 py-5 font-display text-4xl font-bold hover:bg-white/15 active:bg-brand-500">0</button>
                    <button type="button" x-on:click="confirm()" x-show="!(mode === 'phone' && phoneStep === 'pin')"
                            class="flex items-center justify-center rounded-xl bg-brand-500 py-5 hover:bg-brand-600" aria-label="Continuar"><x-icon name="check" class="size-8" /></button>
                    <button type="button" x-on:click="phoneStep = 'phone'; pin = ''" x-show="mode === 'phone' && phoneStep === 'pin'"
                            class="rounded-xl bg-white/5 py-5 text-sm font-bold hover:bg-white/10">Cambiar celular</button>
                </div>
            </div>

            <p x-show="state === 'busy'" class="mt-6 text-steel-300">Verificando…</p>
        </div>

        {{-- Resultado --}}
        <div x-show="state === 'result'" x-cloak x-on:click="reset()"
             :class="result?.status === 'accepted' ? 'bg-success-700' : 'bg-danger-700'"
             class="fixed inset-0 z-10 flex flex-col items-center justify-center px-8 text-center">
            <div class="flex size-28 items-center justify-center rounded-full bg-white/15">
                <template x-if="result?.status === 'accepted'"><x-icon name="check" class="size-16" /></template>
                <template x-if="result?.status !== 'accepted'"><x-icon name="x" class="size-16" /></template>
            </div>
            <h2 class="mt-8 max-w-3xl font-display text-5xl font-bold uppercase leading-tight sm:text-6xl" x-text="result?.title"></h2>
            <p class="mt-4 max-w-2xl text-2xl" x-text="result?.message"></p>
            <ul class="mt-8 space-y-2" x-show="result?.notices?.length">
                <template x-for="notice in result?.notices ?? []" :key="notice">
                    <li class="rounded-lg bg-black/20 px-5 py-3 text-lg font-semibold" x-text="notice"></li>
                </template>
            </ul>
            <p class="mt-10 text-sm text-white/70">Toca la pantalla para continuar.</p>
        </div>
    </main>
</div>
</body>
</html>
