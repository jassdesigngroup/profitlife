<x-layouts.guest title="Activar cuenta">
    <p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-700">Invitación</p>
    <h1 class="mt-1 font-display text-4xl font-bold uppercase tracking-wide text-ink-950">Active su cuenta</h1>
    <p class="mt-2 text-sm text-steel-700">Hola, {{ $user->name }}. Defina una contraseña para <span class="font-semibold text-ink-950">{{ $user->email }}</span>.</p>

    <form method="POST" action="{{ $action }}" class="mt-8 space-y-5">
        @csrf
        <x-input label="Contraseña" name="password" type="password" autocomplete="new-password" hint="Mínimo 10 caracteres, con mayúsculas, minúsculas y números." required autofocus />
        <x-input label="Confirmar contraseña" name="password_confirmation" type="password" autocomplete="new-password" required />
        <x-button type="submit" size="lg" class="w-full">Activar cuenta</x-button>
    </form>
</x-layouts.guest>
