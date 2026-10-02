<div class="space-y-6">
    {{-- Membresía vigente --}}
    @if ($current)
        @php $plan = $current->plan; @endphp
        <section class="relative overflow-hidden rounded-xl bg-surface ring-1 ring-steel-200">
            <div class="absolute inset-y-0 left-0 w-1.5 bg-brand-500" aria-hidden="true"></div>
            <div class="flex flex-col gap-5 p-6 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="font-display text-3xl font-bold uppercase tracking-wide text-ink-950">{{ $plan->name }}</h2>
                        <x-badge :color="$current->status->color()" dot>{{ $current->status->label() }}</x-badge>
                        @if ($current->auto_renews)<x-badge color="info">Renovación automática</x-badge>@endif
                    </div>
                    <p class="mt-1 text-sm text-steel-700">
                        {{ $current->starts_on->format('d/m/Y') }} – {{ $current->ends_on?->format('d/m/Y') }}
                        · {{ $plan->visitLimitLabel() }} · vendida en {{ $current->purchaseLocation?->name }}
                    </p>
                    @if ($current->status === \App\Domain\Memberships\Enums\MembershipStatus::Frozen)
                        <p class="mt-2 text-sm font-semibold text-brand-700">Congelada desde el {{ $current->freezes->first()?->starts_on->format('d/m/Y') }}{{ $current->freezes->first()?->ends_on ? ', se reanuda el '.$current->freezes->first()->ends_on->format('d/m/Y') : '' }}.</p>
                    @endif
                </div>
                <div class="flex items-center gap-6">
                    <div class="text-center">
                        <p class="font-display text-5xl font-bold leading-none text-ink-950">{{ $current->daysRemaining() ?? '∞' }}</p>
                        <p class="text-xs font-bold uppercase tracking-wider text-steel-700">días restantes</p>
                    </div>
                    @if ($plan->allowsFreeze())
                        <div class="text-center">
                            <p class="font-display text-5xl font-bold leading-none text-ink-950">{{ $current->freezeDaysLeft() }}</p>
                            <p class="text-xs font-bold uppercase tracking-wider text-steel-700">días para congelar</p>
                        </div>
                    @endif
                </div>
            </div>
            <div class="flex flex-wrap gap-2 border-t border-steel-200 bg-canvas px-6 py-3">
                @can('freeze', $current)
                    @if ($current->status === \App\Domain\Memberships\Enums\MembershipStatus::Frozen)
                        <x-button variant="secondary" size="sm" wire:click="unfreeze({{ $current->id }})" wire:confirm="¿Reanudar la membresía hoy?">Reanudar</x-button>
                    @elseif ($current->status === \App\Domain\Memberships\Enums\MembershipStatus::Active && $plan->allowsFreeze())
                        <x-button variant="secondary" size="sm" wire:click="openFreeze({{ $current->id }})">Congelar</x-button>
                    @endif
                @endcan
                @can('update', $current)
                    @if (in_array($current->status, [\App\Domain\Memberships\Enums\MembershipStatus::Active, \App\Domain\Memberships\Enums\MembershipStatus::Suspended], true))
                        <x-button variant="secondary" size="sm" wire:click="openStatus({{ $current->id }})">{{ $current->status === \App\Domain\Memberships\Enums\MembershipStatus::Active ? 'Suspender' : 'Reactivar' }}</x-button>
                    @endif
                    <x-button variant="ghost" size="sm" wire:click="toggleAutoRenew({{ $current->id }})">{{ $current->auto_renews ? 'Quitar renovación automática' : 'Activar renovación automática' }}</x-button>
                @endcan
                @can('cancel', $current)
                    <x-button variant="ghost" size="sm" class="text-danger-700" wire:click="openCancel({{ $current->id }})">Cancelar membresía</x-button>
                @endcan
            </div>
        </section>
    @else
        <x-card>
            <x-empty-state icon="heart" title="Sin membresía vigente" description="Venda un plan para que el cliente pueda ingresar." />
        </x-card>
    @endif

    {{-- Historial --}}
    <x-card title="Historial de membresías" :padding="false">
        @can('create', [\App\Domain\Memberships\Models\Membership::class, $member])
            <x-slot:actions><x-button size="sm" icon="plus" wire:click="openSell">Vender membresía</x-button></x-slot:actions>
        @endcan
        @if ($memberships->isEmpty())
            <p class="px-6 py-5 text-sm text-steel-700">Todavía no tiene membresías.</p>
        @else
            <x-table>
                <x-slot:head>
                    <th scope="col" class="px-6 py-3">Plan</th>
                    <th scope="col" class="px-4 py-3">Vigencia</th>
                    <th scope="col" class="px-4 py-3">Precio</th>
                    <th scope="col" class="px-4 py-3">Estado</th>
                    <th scope="col" class="px-6 py-3">Último cambio</th>
                </x-slot:head>
                @foreach ($memberships as $membership)
                    @php $last = $membership->statusHistories->first(); @endphp
                    <tr wire:key="ms-{{ $membership->id }}">
                        <td class="px-6 py-4">
                            <p class="font-semibold text-ink-950">{{ $membership->plan->name }}</p>
                            <p class="text-xs text-steel-700">{{ $membership->purchaseLocation?->name }}@if ($membership->renewed_from_id) · renovación @endif</p>
                        </td>
                        <td class="px-4 py-4 text-steel-700">{{ $membership->starts_on->format('d/m/Y') }} – {{ $membership->ends_on?->format('d/m/Y') }}</td>
                        <td class="px-4 py-4 font-semibold">{{ $membership->price()->format() }}</td>
                        <td class="px-4 py-4"><x-badge :color="$membership->status->color()" dot>{{ $membership->status->label() }}</x-badge></td>
                        <td class="px-6 py-4 text-xs text-steel-700">
                            @if ($last)
                                {{ $last->reason ?? $last->to_status->label() }}<br>
                                <x-datetime :value="$last->created_at" format="d/m/Y H:i" /> · {{ $last->changer?->name ?? 'Automático' }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>

    {{-- Vender --}}
    <x-modal wire:model="showSell" title="Vender membresía" max-width="2xl">
        <form wire:submit="sell" id="sell-form" class="grid gap-4 sm:grid-cols-2">
            <x-select label="Sede de venta" wire:model.live="locationId" :options="$this->sellLocations->pluck('name', 'id')->all()" required />
            <x-select label="Plan" wire:model.live="planId" placeholder="Seleccione un plan" required>
                @foreach ($plans as $p)
                    <option value="{{ $p->id }}">{{ $p->name }} · {{ $p->price()->format() }} · {{ $p->durationLabel() }}</option>
                @endforeach
            </x-select>
            <x-input label="Inicio" type="date" wire:model="startsOn" required />
            @if ($canDiscount)
                <x-input label="Descuento (pesos)" inputmode="numeric" wire:model.live.debounce.400ms="discount" placeholder="0" />
                @if ($quote['discount'] > 0)
                    <x-input label="Motivo del descuento" wire:model="discountReason" required class="sm:col-span-2" />
                @endif
            @endif

            @if ($quote['plan'])
                <div class="rounded-lg bg-canvas p-4 text-sm ring-1 ring-steel-200 sm:col-span-2">
                    <div class="flex justify-between"><span>{{ $quote['plan']->name }}</span><span>{{ $money($quote['plan']->price_cents) }}</span></div>
                    @if ($quote['discount'] > 0)<div class="flex justify-between text-danger-700"><span>Descuento</span><span>− {{ $money($quote['discount']) }}</span></div>@endif
                    @if ($quote['fee'] > 0)<div class="flex justify-between"><span>Matrícula (primera membresía)</span><span>{{ $money($quote['fee']) }}</span></div>@endif
                    <div class="mt-2 flex justify-between border-t border-steel-200 pt-2 text-base font-bold text-ink-950"><span>Total</span><span>{{ $money($quote['total']) }}</span></div>
                </div>
            @endif

            <x-checkbox wire:model.live="payNow" label="Registrar el pago ahora" class="sm:col-span-2" />
            @if ($payNow)
                <x-input label="Valor recibido (pesos)" inputmode="numeric" wire:model="payAmount" hint="Puede ser un abono." />
                <x-select label="Medio de pago" wire:model.live="payMethod" :options="$methods" />
                @if ($payMethod !== 'cash')
                    <x-input label="Referencia o voucher" wire:model="payReference" class="sm:col-span-2" />
                @endif
            @endif
            <x-field-error name="amount" class="sm:col-span-2" />
            <x-field-error name="discount" class="sm:col-span-2" />
        </form>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="open = false">Cancelar</x-button>
            <x-button type="submit" form="sell-form" wire:loading.attr="disabled">Confirmar venta</x-button>
        </x-slot:footer>
    </x-modal>

    {{-- Congelar --}}
    <x-modal wire:model="showFreeze" title="Congelar membresía" max-width="md">
        <form wire:submit="freeze" id="freeze-form" class="space-y-4">
            @if ($target)
                <p class="text-sm text-steel-700">Le quedan <span class="font-bold text-ink-950">{{ $target->freezeDaysLeft() }}</span> días de congelación. Los días congelados se suman al vencimiento.</p>
            @endif
            <x-input label="Se reanuda el (opcional)" type="date" wire:model="resumesOn" hint="Si lo deja vacío, se reanuda manualmente o al agotar los días." />
            <x-input label="Motivo" wire:model="freezeReason" placeholder="Viaje, incapacidad…" />
            <x-field-error name="freeze" />
        </form>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="open = false">Cancelar</x-button>
            <x-button type="submit" form="freeze-form">Congelar desde hoy</x-button>
        </x-slot:footer>
    </x-modal>

    {{-- Cancelar --}}
    <x-modal wire:model="showCancel" title="Cancelar membresía" max-width="md">
        <form wire:submit="cancel" id="cancel-form" class="space-y-4">
            <p class="text-sm text-steel-700">Si el comprobante no tiene pagos, se anulará. Si tiene pagos, se conserva: la devolución de dinero se gestiona aparte.</p>
            <x-input label="Motivo" wire:model="cancelReason" required />
        </form>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="open = false">Volver</x-button>
            <x-button type="submit" form="cancel-form" variant="danger">Cancelar membresía</x-button>
        </x-slot:footer>
    </x-modal>

    {{-- Suspender / reactivar --}}
    <x-modal wire:model="showStatus" :title="$target?->status === \App\Domain\Memberships\Enums\MembershipStatus::Active ? 'Suspender membresía' : 'Reactivar membresía'" max-width="md">
        <form wire:submit="changeStatus" id="status-ms-form" class="space-y-4">
            <x-input label="Motivo" wire:model="statusReason" required />
            <x-field-error name="status" />
        </form>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="open = false">Volver</x-button>
            <x-button type="submit" form="status-ms-form">Confirmar</x-button>
        </x-slot:footer>
    </x-modal>
</div>
