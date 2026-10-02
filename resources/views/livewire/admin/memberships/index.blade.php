<div>
    <x-page-header eyebrow="Operación" title="Membresías" description="Vigencias, renovaciones y cartera." />

    <nav class="mb-5 flex gap-1 overflow-x-auto border-b border-steel-200" aria-label="Vistas">
        @foreach (['current' => 'Vigentes', 'expiring' => 'Vencen en 7 días', 'overdue' => 'En mora', 'all' => 'Todas'] as $key => $label)
            <button type="button" wire:click="$set('view', '{{ $key }}')" @class([
                '-mb-px whitespace-nowrap border-b-2 px-4 py-3 text-sm font-semibold',
                'border-brand-500 text-ink-950' => $view === $key,
                'border-transparent text-steel-700 hover:text-ink-950' => $view !== $key,
            ])>{{ $label }}</button>
        @endforeach
    </nav>

    <x-card :padding="false">
        <div class="grid gap-3 border-b border-steel-200 p-4 sm:grid-cols-3 sm:px-6">
            <x-input wire:model.live.debounce.300ms="search" placeholder="Buscar cliente" aria-label="Buscar cliente" />
            <x-select wire:model.live="plan" :options="$plans" placeholder="Todos los planes" aria-label="Plan" />
            <x-select wire:model.live="status" :options="$statuses" placeholder="Todos los estados" aria-label="Estado" />
        </div>
        @if ($memberships->isEmpty())
            <x-empty-state icon="heart" title="Nada por aquí" description="No hay membresías en esta vista." />
        @else
            <x-table>
                <x-slot:head>
                    <th scope="col" class="px-6 py-3">Cliente</th>
                    <th scope="col" class="px-4 py-3">Plan</th>
                    <th scope="col" class="px-4 py-3">Vigencia</th>
                    <th scope="col" class="px-4 py-3">Estado</th>
                    <th scope="col" class="px-6 py-3"><span class="sr-only">Abrir</span></th>
                </x-slot:head>
                @foreach ($memberships as $membership)
                    <tr wire:key="msi-{{ $membership->id }}" class="hover:bg-canvas">
                        <td class="px-6 py-4">
                            <p class="font-semibold text-ink-950">{{ $membership->member?->full_name }}</p>
                            <p class="text-xs text-steel-700"><span class="font-mono">{{ $membership->member?->member_number }}</span> · {{ $membership->member?->phone }}</p>
                        </td>
                        <td class="px-4 py-4">{{ $membership->plan?->name }}<p class="text-xs text-steel-700">{{ $membership->purchaseLocation?->name }}</p></td>
                        <td class="px-4 py-4 text-steel-700">
                            {{ $membership->starts_on->format('d/m/Y') }} – {{ $membership->ends_on?->format('d/m/Y') }}
                            @if ($membership->status->isCurrent())<p class="text-xs font-semibold text-ink-950">{{ $membership->daysRemaining() }} días</p>@endif
                        </td>
                        <td class="px-4 py-4">
                            <x-badge :color="$membership->status->color()" dot>{{ $membership->status->label() }}</x-badge>
                            @if ($membership->auto_renews)<p class="mt-1 text-xs text-steel-700">Renovación automática</p>@endif
                        </td>
                        <td class="px-6 py-4 text-right">
                            @if ($membership->member)
                                <x-button variant="ghost" size="sm" :href="route('admin.members.show', ['member' => $membership->member_id, 'tab' => 'memberships'])" wire:navigate>Ver</x-button>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-table>
            @if ($memberships->hasPages())
                <div class="border-t border-steel-200 px-6 py-3">{{ $memberships->links() }}</div>
            @endif
        @endif
    </x-card>
</div>
