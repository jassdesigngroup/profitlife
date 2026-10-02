<x-layouts.guest title="Invitación no válida">
    <h1 class="font-display text-4xl font-bold uppercase tracking-wide text-ink-950">Enlace no válido</h1>
    <x-alert type="warning" class="mt-6">
        El enlace de invitación venció, ya se utilizó o no es correcto. Pida a un administrador que le reenvíe la invitación.
    </x-alert>
    <p class="mt-8 text-center text-sm">
        <a href="{{ route('login') }}" class="font-semibold text-brand-700 hover:underline">Ir a iniciar sesión</a>
    </p>
</x-layouts.guest>
