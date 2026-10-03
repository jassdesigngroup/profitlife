@props(['title' => null])
@php
    $user = auth()->user();
    $nav = [
        ['label' => 'Inicio', 'route' => 'admin.dashboard', 'active' => 'admin.dashboard', 'icon' => 'home', 'can' => 'dashboard.view'],
        ['label' => 'Clientes', 'route' => 'admin.members.index', 'active' => 'admin.members.*', 'icon' => 'heart', 'can' => 'members.view'],
        ['label' => 'Agenda', 'route' => 'admin.appointments.index', 'active' => 'admin.appointments.*', 'icon' => 'calendar', 'can' => 'appointments.view'],
        ['label' => 'Asistencia', 'route' => 'admin.check-ins.index', 'active' => 'admin.check-ins.*', 'icon' => 'enter', 'can' => 'check-ins.view'],
        ['label' => 'Ejercicios', 'route' => 'admin.training.exercises', 'active' => 'admin.training.exercises', 'icon' => 'bolt', 'can' => 'training.view'],
        ['label' => 'Plantillas', 'route' => 'admin.training.templates', 'active' => 'admin.training.templates', 'icon' => 'squares', 'can' => 'training.view'],
        ['label' => 'Membresías', 'route' => 'admin.memberships.index', 'active' => 'admin.memberships.*', 'icon' => 'heart', 'can' => 'memberships.view'],
        ['label' => 'Pagos', 'route' => 'admin.payments.index', 'active' => 'admin.payments.*', 'icon' => 'chart', 'can' => 'payments.view'],
        ['label' => 'Sedes', 'route' => 'admin.locations.index', 'active' => 'admin.locations.*', 'icon' => 'map-pin', 'can' => 'locations.view'],
        ['label' => 'Staff', 'route' => 'admin.staff.index', 'active' => 'admin.staff.*', 'icon' => 'users', 'can' => 'staff.view'],
    ];
    $adminNav = [
        ['label' => 'Servicios', 'route' => 'admin.services.index', 'active' => 'admin.services.*', 'icon' => 'clipboard', 'can' => 'services.manage'],
        ['label' => 'Planes', 'route' => 'admin.plans.index', 'active' => 'admin.plans.*', 'icon' => 'bolt', 'can' => 'memberships.manage-plans'],
        ['label' => 'Ajustes', 'route' => 'admin.settings', 'active' => 'admin.settings', 'icon' => 'squares', 'can' => 'settings.update'],
        ['label' => 'Roles y permisos', 'route' => 'admin.roles.index', 'active' => 'admin.roles.*', 'icon' => 'shield', 'can' => 'roles.view'],
        ['label' => 'Consentimientos', 'route' => 'admin.consents.index', 'active' => 'admin.consents.*', 'icon' => 'check-circle', 'can' => 'consent-templates.manage'],
        ['label' => 'Auditoría', 'route' => 'admin.audit.index', 'active' => 'admin.audit.*', 'icon' => 'clipboard', 'can' => 'audit.view'],
    ];
    $visibleAdminNav = array_filter($adminNav, fn ($item) => $user->can($item['can']));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ? $title.' · ' : '' }}{{ app(\App\Domain\Settings\Services\Settings::class)->brandName() }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full" x-data="{ sidebar: false }">
    {{-- Fondo del menú móvil --}}
    <div x-show="sidebar" x-cloak x-transition.opacity class="fixed inset-0 z-40 bg-ink-950/60 lg:hidden" x-on:click="sidebar = false"></div>

    {{-- Barra lateral --}}
    <aside
        class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col bg-ink-950 transition-transform duration-200 lg:translate-x-0"
        x-bind:class="sidebar ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
        aria-label="Navegación principal"
    >
        <div class="flex h-16 shrink-0 items-center justify-between px-5">
            <a href="{{ route('admin.dashboard') }}" wire:navigate><x-brand dark /></a>
            <button type="button" class="rounded-md p-1.5 text-steel-300 hover:text-white lg:hidden" x-on:click="sidebar = false">
                <span class="sr-only">Cerrar menú</span>
                <x-icon name="x" />
            </button>
        </div>

        <nav class="flex-1 overflow-y-auto px-3 py-4">
            <ul class="space-y-1">
                @foreach ($nav as $item)
                    @can($item['can'])
                        @php $active = request()->routeIs($item['active']); @endphp
                        <li>
                            <a href="{{ route($item['route']) }}" wire:navigate @class([
                                'group relative flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition-colors',
                                'bg-white/10 text-white' => $active,
                                'text-steel-300 hover:bg-white/5 hover:text-white' => ! $active,
                            ]) @if ($active) aria-current="page" @endif>
                                @if ($active)<span class="absolute inset-y-2 left-0 w-1 rounded-r bg-brand-500" aria-hidden="true"></span>@endif
                                <x-icon :name="$item['icon']" @class(['size-5', 'text-brand-500' => $active]) />
                                {{ $item['label'] }}
                            </a>
                        </li>
                    @endcan
                @endforeach
            </ul>

            @if ($visibleAdminNav !== [])
                <p class="mt-8 px-3 text-[11px] font-bold uppercase tracking-[0.2em] text-steel-500">Administración</p>
                <ul class="mt-2 space-y-1">
                    @foreach ($visibleAdminNav as $item)
                        @php $active = request()->routeIs($item['active']); @endphp
                        <li>
                            <a href="{{ route($item['route']) }}" wire:navigate @class([
                                'group relative flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition-colors',
                                'bg-white/10 text-white' => $active,
                                'text-steel-300 hover:bg-white/5 hover:text-white' => ! $active,
                            ]) @if ($active) aria-current="page" @endif>
                                @if ($active)<span class="absolute inset-y-2 left-0 w-1 rounded-r bg-brand-500" aria-hidden="true"></span>@endif
                                <x-icon :name="$item['icon']" @class(['size-5', 'text-brand-500' => $active]) />
                                {{ $item['label'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </nav>

        <div class="border-t border-white/10 p-4">
            <p class="truncate text-sm font-semibold text-white">{{ $user->name }}</p>
            <p class="truncate text-xs text-steel-300">{{ collect($user->roleEnums())->map->label()->implode(' · ') }}</p>
        </div>
    </aside>

    <div class="flex min-h-full flex-col lg:pl-64">
        {{-- Barra superior --}}
        <header class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-steel-200 bg-surface/95 px-4 backdrop-blur sm:px-6 lg:px-8">
            <button type="button" class="-ml-1 rounded-md p-2 text-ink-950 hover:bg-steel-100 lg:hidden" x-on:click="sidebar = true">
                <span class="sr-only">Abrir menú</span>
                <x-icon name="menu" />
            </button>

            <livewire:admin.layout.location-switcher />

            <div class="ml-auto flex items-center gap-2">
                <div class="relative" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape="open = false">
                    <button type="button" class="flex items-center gap-2 rounded-full py-1 pl-1 pr-2 hover:bg-steel-100" x-on:click="open = ! open" x-bind:aria-expanded="open" aria-haspopup="menu">
                        <span class="flex size-8 items-center justify-center rounded-full bg-ink-950 text-xs font-bold text-white">{{ $user->initials() }}</span>
                        <span class="hidden text-sm font-semibold text-ink-950 sm:block">{{ \Illuminate\Support\Str::before($user->name, ' ') }}</span>
                        <x-icon name="chevron-down" class="size-4 text-steel-500" />
                    </button>
                    <div x-show="open" x-cloak x-transition.origin.top.right class="absolute right-0 mt-2 w-60 overflow-hidden rounded-xl bg-surface shadow-xl ring-1 ring-steel-200" role="menu">
                        <div class="border-b border-steel-200 px-4 py-3">
                            <p class="truncate text-sm font-semibold text-ink-950">{{ $user->name }}</p>
                            <p class="truncate text-xs text-steel-700">{{ $user->email }}</p>
                        </div>
                        <a href="{{ route('admin.profile') }}" class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-ink-950 hover:bg-steel-100" role="menuitem">
                            <x-icon name="user" class="size-4 text-steel-500" /> Mi perfil
                        </a>
                        <a href="{{ route('admin.security') }}" class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-ink-950 hover:bg-steel-100" role="menuitem">
                            <x-icon name="key" class="size-4 text-steel-500" /> Seguridad
                        </a>
                        <form method="POST" action="{{ route('logout') }}" class="border-t border-steel-200">
                            @csrf
                            <button type="submit" class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-danger-700 hover:bg-danger-50" role="menuitem">
                                <x-icon name="logout" class="size-4" /> Cerrar sesión
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
            <div class="mx-auto max-w-7xl">
                <x-flash />
                {{ $slot }}
            </div>
        </main>
    </div>
    {{-- Avisos emergentes: los componentes Livewire emiten el evento "toast". --}}
    <div
        x-data="{ toasts: [], add(e) { const id = Date.now() + Math.random(); this.toasts.push({ id, message: e.detail.message, type: e.detail.type ?? 'success' }); setTimeout(() => this.toasts = this.toasts.filter(t => t.id !== id), 4500) } }"
        x-on:toast.window="add($event)"
        class="pointer-events-none fixed inset-x-0 bottom-0 z-[60] flex flex-col items-center gap-2 p-4 sm:items-end sm:p-6"
        aria-live="polite"
    >
        <template x-for="toast in toasts" :key="toast.id">
            <div x-transition class="pointer-events-auto flex w-full max-w-sm items-center gap-3 rounded-xl bg-ink-950 px-4 py-3 text-sm font-medium text-white shadow-2xl">
                <span class="size-2 shrink-0 rounded-full" :class="toast.type === 'danger' ? 'bg-red-400' : (toast.type === 'warning' ? 'bg-amber-400' : 'bg-brand-500')"></span>
                <span x-text="toast.message"></span>
            </div>
        </template>
    </div>
</body>
</html>
