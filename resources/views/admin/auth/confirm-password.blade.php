<x-layouts.guest title="Confirmar contraseña">
    <h1 class="font-display text-4xl font-bold uppercase tracking-wide text-ink-950">Confirme su identidad</h1>
    <p class="mt-2 text-sm text-steel-700">Por seguridad, escriba su contraseña para continuar.</p>

    <form method="POST" action="{{ route('password.confirm.store') }}" class="mt-8 space-y-5">
        @csrf
        <x-input label="Contraseña" name="password" type="password" autocomplete="current-password" required autofocus />
        <x-button type="submit" size="lg" class="w-full">Confirmar</x-button>
    </form>
</x-layouts.guest>
