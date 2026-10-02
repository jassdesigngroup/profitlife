<x-layouts.guest title="Nueva contraseña">
    <h1 class="font-display text-4xl font-bold uppercase tracking-wide text-ink-950">Nueva contraseña</h1>
    <p class="mt-2 text-sm text-steel-700">Mínimo 10 caracteres, con mayúsculas, minúsculas y números.</p>

    <form method="POST" action="{{ route('password.update') }}" class="mt-8 space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <x-input label="Correo electrónico" name="email" type="email" :value="old('email', $request->email)" autocomplete="username" required />
        <x-input label="Nueva contraseña" name="password" type="password" autocomplete="new-password" required />
        <x-input label="Confirmar contraseña" name="password_confirmation" type="password" autocomplete="new-password" required />
        <x-button type="submit" size="lg" class="w-full">Guardar contraseña</x-button>
    </form>
</x-layouts.guest>
