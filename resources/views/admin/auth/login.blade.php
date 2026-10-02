<x-layouts.guest title="Iniciar sesión">
    <h1 class="font-display text-4xl font-bold uppercase tracking-wide text-ink-950">Bienvenido</h1>
    <p class="mt-2 text-sm text-steel-700">Ingrese con su correo y contraseña.</p>

    <x-flash class="mt-6" />

    <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5">
        @csrf
        <x-input label="Correo electrónico" name="email" type="email" :value="old('email')" autocomplete="username" required autofocus />
        <div>
            <x-input label="Contraseña" name="password" type="password" autocomplete="current-password" required />
            <div class="mt-2 text-right">
                <a href="{{ route('password.request') }}" class="text-sm font-semibold text-brand-700 hover:underline">¿Olvidó su contraseña?</a>
            </div>
        </div>
        <x-checkbox label="Mantener la sesión iniciada" name="remember" value="1" />
        <x-button type="submit" size="lg" class="w-full">Ingresar</x-button>
    </form>
</x-layouts.guest>
