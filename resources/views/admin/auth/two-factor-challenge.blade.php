<x-layouts.guest title="Verificación en dos pasos">
    <div x-data="{ recovery: {{ $errors->has('recovery_code') ? 'true' : 'false' }} }">
        <h1 class="font-display text-4xl font-bold uppercase tracking-wide text-ink-950">Verificación</h1>
        <p class="mt-2 text-sm text-steel-700" x-show="! recovery">Escriba el código de 6 dígitos de su aplicación de autenticación.</p>
        <p class="mt-2 text-sm text-steel-700" x-show="recovery" x-cloak>Escriba uno de sus códigos de recuperación.</p>

        <form method="POST" action="{{ url('/two-factor-challenge') }}" class="mt-8 space-y-5">
            @csrf
            <div x-show="! recovery">
                <x-input label="Código" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" autofocus x-bind:disabled="recovery" />
            </div>
            <div x-show="recovery" x-cloak>
                <x-input label="Código de recuperación" name="recovery_code" autocomplete="off" x-bind:disabled="! recovery" />
            </div>
            <x-button type="submit" size="lg" class="w-full">Verificar</x-button>
        </form>

        <button type="button" class="mt-6 w-full text-center text-sm font-semibold text-brand-700 hover:underline" x-on:click="recovery = ! recovery">
            <span x-show="! recovery">Usar un código de recuperación</span>
            <span x-show="recovery" x-cloak>Usar la aplicación de autenticación</span>
        </button>
    </div>
</x-layouts.guest>
