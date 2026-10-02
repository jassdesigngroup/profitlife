<x-layouts.guest title="Recuperar contraseña">
    <h1 class="font-display text-4xl font-bold uppercase tracking-wide text-ink-950">Recuperar acceso</h1>
    <p class="mt-2 text-sm text-steel-700">Le enviaremos un enlace para definir una nueva contraseña.</p>

    <x-flash class="mt-6" />

    <form method="POST" action="{{ route('password.email') }}" class="mt-8 space-y-5">
        @csrf
        <x-input label="Correo electrónico" name="email" type="email" :value="old('email')" autocomplete="username" required autofocus />
        <x-button type="submit" size="lg" class="w-full">Enviar enlace</x-button>
    </form>

    <p class="mt-8 text-center text-sm">
        <a href="{{ route('login') }}" class="font-semibold text-brand-700 hover:underline">Volver a iniciar sesión</a>
    </p>
</x-layouts.guest>
