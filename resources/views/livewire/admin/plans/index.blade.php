<div>
    <x-page-header eyebrow="Administración" title="Planes" description="Lo que se vende. Cambiar un precio no afecta a las membresías ya vendidas.">
        <x-slot:actions>
            <x-button :href="route('admin.plans.create')" icon="plus" wire:navigate>Nuevo plan</x-button>
        </x-slot:actions>
    </x-page-header>

    @if ($plans->isEmpty())
        <x-card><x-empty-state icon="heart" title="Sin planes" description="Cree el primer plan para empezar a vender membresías." /></x-card>
    @else
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($plans as $plan)
                <article wire:key="plan-{{ $plan->id }}" @class([
                    'flex flex-col rounded-xl bg-surface p-5 ring-1',
                    'ring-steel-200' => $plan->is_active,
                    'ring-steel-200 opacity-70' => ! $plan->is_active,
                ])>
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="font-display text-2xl font-bold uppercase tracking-wide text-ink-950">{{ $plan->name }}</h2>
                            <p class="text-sm text-steel-700">{{ $plan->durationLabel() }} · {{ $plan->visitLimitLabel() }}</p>
                        </div>
                        <x-badge :color="$plan->is_active ? 'success' : 'neutral'" dot>{{ $plan->is_active ? 'En venta' : 'Inactivo' }}</x-badge>
                    </div>
                    <p class="mt-4 font-display text-4xl font-bold text-ink-950">{{ $plan->price()->format() }}</p>
                    @if ($plan->enrollment_fee_cents > 0)
                        <p class="text-xs text-steel-700">+ matrícula {{ $plan->enrollmentFee()->format() }} (solo la primera vez)</p>
                    @endif
                    <dl class="mt-4 space-y-1 text-sm text-steel-700">
                        <div><span class="font-semibold text-ink-950">Sedes:</span> {{ $plan->access_scope === \App\Domain\Memberships\Enums\AccessScope::AllLocations ? 'Todas' : ($plan->locations->pluck('name')->implode(', ') ?: '—') }}</div>
                        <div><span class="font-semibold text-ink-950">Congelación:</span> {{ $plan->allowsFreeze() ? 'hasta '.$plan->max_freeze_days.' días' : 'no permitida' }}</div>
                        <div><span class="font-semibold text-ink-950">Renovación:</span> {{ $plan->auto_renews ? 'automática' : 'manual' }}</div>
                        @if ($plan->tax_rate_bps > 0)<div><span class="font-semibold text-ink-950">IVA:</span> {{ $plan->tax_rate_bps / 100 }} % incluido</div>@endif
                        <div><span class="font-semibold text-ink-950">Membresías vigentes:</span> {{ $plan->current_count }}</div>
                    </dl>
                    <div class="mt-auto flex gap-2 pt-5">
                        <x-button variant="secondary" size="sm" :href="route('admin.plans.edit', $plan)" wire:navigate>Editar</x-button>
                        <x-button variant="ghost" size="sm" wire:click="toggle({{ $plan->id }})">{{ $plan->is_active ? 'Dejar de vender' : 'Volver a vender' }}</x-button>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</div>
