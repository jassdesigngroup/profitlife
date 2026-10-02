<div>
    <x-page-header eyebrow="Administración" title="Roles y permisos" description="Los roles son globales; el alcance por sede se define al asignar sedes a cada persona." />

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($roles as $role)
            @php $enum = \App\Domain\Identity\Enums\RoleName::tryFrom($role->name); @endphp
            <a href="{{ route('admin.roles.edit', $role) }}" wire:navigate class="group rounded-xl bg-surface p-5 ring-1 ring-steel-200 transition hover:ring-brand-500">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="font-display text-2xl font-bold uppercase tracking-wide text-ink-950">{{ $enum?->label() ?? $role->name }}</p>
                        <p class="text-xs font-mono text-steel-700">{{ $role->name }}</p>
                    </div>
                    @if ($enum?->requiresTwoFactor())
                        <x-badge color="dark">2FA obligatorio</x-badge>
                    @endif
                </div>
                <div class="mt-5 flex items-end justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-steel-700">Permisos</p>
                        <p class="font-display text-3xl font-bold text-ink-950">{{ $role->permissions_count }}<span class="text-lg text-steel-500">/{{ $totalPermissions }}</span></p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs font-bold uppercase tracking-wider text-steel-700">Usuarios</p>
                        <p class="font-display text-3xl font-bold text-ink-950">{{ $role->users_count }}</p>
                    </div>
                </div>
                <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-steel-100">
                    <div class="h-full rounded-full bg-brand-500" style="width: {{ $totalPermissions ? round($role->permissions_count / $totalPermissions * 100) : 0 }}%"></div>
                </div>
            </a>
        @endforeach
    </div>
</div>
