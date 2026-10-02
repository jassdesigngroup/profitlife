<x-layouts.admin title="Mi perfil">
    <x-page-header eyebrow="Cuenta" title="Mi perfil" />

    @if ($user->requiresTwoFactor() && ! $user->hasConfirmedTwoFactor())
        <x-alert type="warning" title="Verificación en dos pasos pendiente" class="mb-6">
            Su rol la exige para usar el panel. <a href="{{ route('admin.security') }}" class="font-bold underline">Configúrela ahora</a>.
        </x-alert>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card title="Cuenta" class="lg:col-span-1">
            <div class="flex items-center gap-4">
                <span class="flex size-14 items-center justify-center rounded-full bg-ink-950 font-display text-xl font-bold text-white">{{ $user->initials() }}</span>
                <div class="min-w-0">
                    <p class="truncate font-bold text-ink-950">{{ $user->name }}</p>
                    <p class="truncate text-sm text-steel-700">{{ $user->email }}</p>
                </div>
            </div>
            <dl class="mt-6 space-y-3 text-sm">
                <div>
                    <dt class="text-xs font-bold uppercase tracking-wider text-steel-700">Roles</dt>
                    <dd class="mt-1 flex flex-wrap gap-1">
                        @forelse ($user->roleEnums() as $role)
                            <x-badge color="brand">{{ $role->label() }}</x-badge>
                        @empty
                            <span class="text-steel-700">Sin rol</span>
                        @endforelse
                    </dd>
                </div>
                @if ($staff)
                    <div>
                        <dt class="text-xs font-bold uppercase tracking-wider text-steel-700">Sedes</dt>
                        <dd class="mt-1 text-ink-950">{{ $staff->locations->pluck('name')->implode(', ') ?: '—' }}</dd>
                    </div>
                    @if ($staff->job_title)
                        <div>
                            <dt class="text-xs font-bold uppercase tracking-wider text-steel-700">Cargo</dt>
                            <dd class="mt-1 text-ink-950">{{ $staff->job_title }}</dd>
                        </div>
                    @endif
                @endif
                <div>
                    <dt class="text-xs font-bold uppercase tracking-wider text-steel-700">Último acceso</dt>
                    <dd class="mt-1 text-ink-950"><x-datetime :value="$user->last_login_at" /></dd>
                </div>
            </dl>
            <p class="mt-6 text-xs text-steel-700">Para cambiar su nombre o correo, contacte a un administrador.</p>
        </x-card>

        <div class="space-y-6 lg:col-span-2">
            <x-card title="Contraseña" description="Use al menos 10 caracteres, con mayúsculas, minúsculas y números.">
                @if (session('status') === 'password-updated')
                    <x-alert type="success" class="mb-4">Contraseña actualizada.</x-alert>
                @endif
                <form method="POST" action="{{ route('user-password.update') }}" class="grid gap-5 sm:grid-cols-2">
                    @csrf
                    @method('PUT')
                    <x-input label="Contraseña actual" name="current_password" type="password" bag="updatePassword" autocomplete="current-password" required class="sm:col-span-2" />
                    <x-input label="Nueva contraseña" name="password" type="password" bag="updatePassword" autocomplete="new-password" required />
                    <x-input label="Confirmar nueva contraseña" name="password_confirmation" type="password" bag="updatePassword" autocomplete="new-password" required />
                    <div class="sm:col-span-2 flex justify-end">
                        <x-button type="submit">Cambiar contraseña</x-button>
                    </div>
                </form>
            </x-card>

            <x-card title="Verificación en dos pasos">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3">
                        <span @class([
                            'flex size-10 items-center justify-center rounded-full',
                            'bg-success-50 text-success-700' => $user->hasConfirmedTwoFactor(),
                            'bg-warning-50 text-warning-700' => ! $user->hasConfirmedTwoFactor(),
                        ])><x-icon :name="$user->hasConfirmedTwoFactor() ? 'shield' : 'warning'" /></span>
                        <div>
                            <p class="font-semibold text-ink-950">{{ $user->hasConfirmedTwoFactor() ? 'Activada' : 'Desactivada' }}</p>
                            <p class="text-sm text-steel-700">{{ $user->requiresTwoFactor() ? 'Obligatoria para su rol.' : 'Recomendada para proteger su cuenta.' }}</p>
                        </div>
                    </div>
                    <x-button variant="secondary" :href="route('admin.security')" icon="key">Gestionar</x-button>
                </div>
            </x-card>
        </div>
    </div>
</x-layouts.admin>
