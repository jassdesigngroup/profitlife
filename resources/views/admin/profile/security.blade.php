<x-layouts.admin title="Seguridad">
    <a href="{{ route('admin.profile') }}" class="mb-4 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-700 hover:underline">
        <x-icon name="arrow-left" class="size-4" /> Mi perfil
    </a>
    <x-page-header eyebrow="Cuenta" title="Verificación en dos pasos" description="Proteja su cuenta con un código temporal de una aplicación como Google Authenticator, Microsoft Authenticator o 1Password." />

    <div class="max-w-2xl space-y-6">
        @if ($user->hasConfirmedTwoFactor())
            <x-card>
                <div class="flex items-center gap-3">
                    <span class="flex size-10 items-center justify-center rounded-full bg-success-50 text-success-700"><x-icon name="shield" /></span>
                    <div>
                        <p class="font-bold text-ink-950">La verificación en dos pasos está activa</p>
                        <p class="text-sm text-steel-700">Se le pedirá un código cada vez que inicie sesión.</p>
                    </div>
                </div>

                @if ($recoveryCodes !== [])
                    <x-alert type="warning" title="Guarde sus códigos de recuperación" class="mt-6">
                        Úselos si pierde acceso a su teléfono. Cada código sirve una sola vez y no se volverán a mostrar.
                    </x-alert>
                    <div class="mt-4 grid grid-cols-2 gap-2 rounded-lg bg-ink-950 p-4 font-mono text-sm text-white">
                        @foreach ($recoveryCodes as $code)
                            <span>{{ $code }}</span>
                        @endforeach
                    </div>
                @endif

                <div class="mt-6 flex flex-wrap gap-2 border-t border-steel-200 pt-5">
                    <form method="POST" action="{{ url('/user/two-factor-recovery-codes') }}">
                        @csrf
                        <x-button type="submit" variant="secondary">Generar nuevos códigos de recuperación</x-button>
                    </form>
                    @unless ($user->requiresTwoFactor())
                        <form method="POST" action="{{ url('/user/two-factor-authentication') }}">
                            @csrf
                            @method('DELETE')
                            <x-button type="submit" variant="ghost" class="text-danger-700">Desactivar</x-button>
                        </form>
                    @endunless
                </div>
                <x-field-error name="two_factor" bag="disableTwoFactorAuthentication" />
            </x-card>
        @elseif ($pending)
            <x-card title="1. Escanee el código" description="Abra su aplicación de autenticación y escanee este código QR.">
                <div class="flex flex-col items-center gap-6 sm:flex-row sm:items-start">
                    <div class="shrink-0 rounded-xl bg-white p-3 ring-1 ring-steel-200 [&_svg]:size-44">{!! $qrCode !!}</div>
                    <div class="text-sm">
                        <p class="text-steel-700">¿No puede escanearlo? Escriba esta clave en la aplicación:</p>
                        <p class="mt-2 break-all rounded-lg bg-canvas px-3 py-2 font-mono font-semibold tracking-wider text-ink-950">{{ $setupKey }}</p>
                    </div>
                </div>
            </x-card>
            <x-card title="2. Confirme con un código" description="Escriba el código de 6 dígitos que muestra la aplicación.">
                <form method="POST" action="{{ url('/user/confirmed-two-factor-authentication') }}" class="flex flex-col gap-3 sm:flex-row sm:items-start">
                    @csrf
                    <x-input name="code" label="Código" bag="confirmTwoFactorAuthentication" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required autofocus class="sm:w-48" />
                    <x-button type="submit" class="sm:mt-7">Activar</x-button>
                </form>
            </x-card>
        @else
            <x-card>
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="font-bold text-ink-950">Aún no está activada</p>
                        <p class="text-sm text-steel-700">{{ $user->requiresTwoFactor() ? 'Su rol la exige para entrar al panel.' : 'Le recomendamos activarla.' }}</p>
                    </div>
                    <form method="POST" action="{{ url('/user/two-factor-authentication') }}">
                        @csrf
                        <x-button type="submit" icon="shield">Activar verificación</x-button>
                    </form>
                </div>
            </x-card>
        @endif
    </div>
</x-layouts.admin>
