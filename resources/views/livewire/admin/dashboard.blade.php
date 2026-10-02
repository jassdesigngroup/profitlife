@php
    $hour = now()->setTimezone(app(\App\Domain\Settings\Services\Settings::class)->displayTimezone())->hour;
    $greeting = $hour < 12 ? 'Buenos días' : ($hour < 19 ? 'Buenas tardes' : 'Buenas noches');
    $cards = [
        ['label' => 'Asistencias de hoy', 'icon' => 'enter', 'value' => $kpis['checkins'], 'phase' => null, 'route' => 'admin.check-ins.index'],
        ['label' => 'Membresías activas', 'icon' => 'heart', 'value' => $kpis['memberships'], 'phase' => null, 'route' => 'admin.memberships.index'],
        ['label' => 'Citas de hoy', 'icon' => 'calendar', 'value' => null, 'phase' => 'Fase 6'],
        ['label' => 'Ingresos del mes', 'icon' => 'chart', 'value' => $kpis['income'], 'phase' => null, 'route' => 'admin.payments.index'],
    ];
@endphp
<div>
    {{-- Encabezado --}}
    <section class="relative mb-8 overflow-hidden rounded-2xl bg-ink-950 px-6 py-8 sm:px-10">
        <div class="pointer-events-none absolute -right-20 -top-24 size-80 rounded-full bg-brand-500/25 blur-3xl" aria-hidden="true"></div>
        <div class="pointer-events-none absolute right-24 top-0 h-full w-px rotate-12 bg-gradient-to-b from-transparent via-brand-500/70 to-transparent" aria-hidden="true"></div>
        <div class="relative">
            <p class="text-xs font-bold uppercase tracking-[0.22em] text-brand-500">{{ $currentLocation?->name ?? 'Todas las sedes' }}</p>
            <h1 class="mt-2 font-display text-4xl font-bold uppercase tracking-wide text-white sm:text-5xl">{{ $greeting }}, {{ \Illuminate\Support\Str::before($user->name, ' ') }}</h1>
            <p class="mt-2 max-w-xl text-sm text-steel-300">Este es el resumen de su operación. Los indicadores diarios se activan a medida que se habilitan los módulos.</p>
        </div>
    </section>

    {{-- Indicadores de las fases siguientes --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($cards as $card)
            <div class="relative overflow-hidden rounded-xl bg-surface p-5 ring-1 ring-steel-200">
                <div class="flex items-start justify-between">
                    <p class="text-sm font-semibold text-steel-700">{{ $card['label'] }}</p>
                    <span class="flex size-9 items-center justify-center rounded-lg bg-brand-50 text-brand-700"><x-icon :name="$card['icon']" class="size-5" /></span>
                </div>
                @if ($card['phase'])
                    <p class="mt-3 font-display text-4xl font-bold text-steel-300" aria-label="Sin datos todavía">—</p>
                    <x-badge class="mt-3">Disponible en {{ $card['phase'] }}</x-badge>
                @elseif ($card['value'] === null)
                    <p class="mt-3 font-display text-4xl font-bold text-steel-300">—</p>
                    <p class="mt-3 text-xs text-steel-700">Sin permiso para ver este dato.</p>
                @else
                    <a href="{{ route($card['route']) }}" wire:navigate class="mt-3 block font-display text-4xl font-bold text-ink-950 hover:text-brand-700">{{ $card['value'] }}</a>
                    @if ($card['label'] === 'Ingresos del mes' && $kpis['overdue'])
                        <x-badge color="danger" class="mt-3">{{ $kpis['overdue'] }} comprobantes en mora</x-badge>
                    @else
                        <p class="mt-3 text-xs text-steel-700">{{ $currentLocation?->name ?? 'Todas las sedes' }}</p>
                    @endif
                @endif
            </div>
        @endforeach
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-3">
        {{-- Estructura actual --}}
        <x-card title="Estructura" description="Datos actuales{{ $currentLocation ? ' de '.$currentLocation->name : '' }}." class="lg:col-span-1">
            <dl class="divide-y divide-steel-200">
                @foreach ([['members', 'Clientes activos', 'heart', 'admin.members.index'], ['locations', 'Sedes activas', 'map-pin', 'admin.locations.index'], ['rooms', 'Salas activas', 'squares', 'admin.locations.index'], ['staff', 'Staff activo', 'users', 'admin.staff.index']] as [$key, $label, $icon, $route])
                    @if ($stats[$key] !== null)
                        <div class="flex items-center justify-between py-3 first:pt-0 last:pb-0">
                            <dt class="flex items-center gap-2 text-sm font-medium text-ink-950"><x-icon :name="$icon" class="size-4 text-steel-500" /> {{ $label }}</dt>
                            <dd><a href="{{ route($route) }}" wire:navigate class="font-display text-2xl font-bold text-ink-950 hover:text-brand-700">{{ $stats[$key] }}</a></dd>
                        </div>
                    @endif
                @endforeach
            </dl>
        </x-card>

        {{-- Accesos rápidos --}}
        <x-card title="Accesos rápidos" class="lg:col-span-1">
            <div class="grid gap-2">
                @can('members.create')
                    <a href="{{ route('admin.members.create') }}" wire:navigate class="flex items-center justify-between rounded-lg px-3 py-3 ring-1 ring-steel-200 hover:bg-canvas hover:ring-brand-200">
                        <span class="flex items-center gap-3 text-sm font-semibold text-ink-950"><x-icon name="heart" class="size-5 text-brand-700" /> Registrar un cliente</span>
                        <x-icon name="chevron-right" class="size-4 text-steel-500" />
                    </a>
                @endcan
                @can('staff.create')
                    <a href="{{ route('admin.staff.create') }}" wire:navigate class="flex items-center justify-between rounded-lg px-3 py-3 ring-1 ring-steel-200 hover:bg-canvas hover:ring-brand-200">
                        <span class="flex items-center gap-3 text-sm font-semibold text-ink-950"><x-icon name="paper-plane" class="size-5 text-brand-700" /> Invitar a un miembro del staff</span>
                        <x-icon name="chevron-right" class="size-4 text-steel-500" />
                    </a>
                @endcan
                @can('locations.create')
                    <a href="{{ route('admin.locations.create') }}" wire:navigate class="flex items-center justify-between rounded-lg px-3 py-3 ring-1 ring-steel-200 hover:bg-canvas hover:ring-brand-200">
                        <span class="flex items-center gap-3 text-sm font-semibold text-ink-950"><x-icon name="building" class="size-5 text-brand-700" /> Crear una sede</span>
                        <x-icon name="chevron-right" class="size-4 text-steel-500" />
                    </a>
                @endcan
                <a href="{{ route('admin.profile') }}" class="flex items-center justify-between rounded-lg px-3 py-3 ring-1 ring-steel-200 hover:bg-canvas hover:ring-brand-200">
                    <span class="flex items-center gap-3 text-sm font-semibold text-ink-950"><x-icon name="key" class="size-5 text-brand-700" /> Mi perfil y seguridad</span>
                    <x-icon name="chevron-right" class="size-4 text-steel-500" />
                </a>
            </div>
        </x-card>

        {{-- Actividad reciente --}}
        @can('audit.view')
            <x-card title="Actividad reciente" class="lg:col-span-1">
                <x-slot:actions>
                    <x-button variant="link" size="sm" :href="route('admin.audit.index')" wire:navigate>Ver todo</x-button>
                </x-slot:actions>
                @forelse ($activity as $entry)
                    <div class="flex gap-3 py-2.5 first:pt-0 last:pb-0">
                        <span class="mt-1.5 size-2 shrink-0 rounded-full bg-brand-500" aria-hidden="true"></span>
                        <div class="min-w-0 text-sm">
                            <p class="truncate font-semibold text-ink-950">{{ $entry->description }}</p>
                            <p class="text-xs text-steel-700">{{ $entry->causer?->name ?? 'Sistema' }} · <x-datetime :value="$entry->created_at" format="d/m H:i" /></p>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-steel-700">Todavía no hay actividad registrada.</p>
                @endforelse
            </x-card>
        @endcan
    </div>
</div>
