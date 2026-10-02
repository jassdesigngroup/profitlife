<div class="space-y-6">
    <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl bg-surface p-5 ring-1 ring-steel-200">
            <p class="text-xs font-bold uppercase tracking-wider text-steel-700">Saldo pendiente</p>
            <p @class(['mt-1 font-display text-4xl font-bold', 'text-danger-700' => $balance > 0, 'text-ink-950' => $balance === 0])>{{ \App\Support\Money::ofCents($balance)->format() }}</p>
        </div>
        <div class="rounded-xl bg-surface p-5 ring-1 ring-steel-200 sm:col-span-2">
            <p class="text-sm text-steel-700">Los comprobantes son documentos internos de cobro; <span class="font-semibold text-ink-950">no son factura electrónica</span>.</p>
        </div>
    </div>

    @forelse ($invoices as $invoice)
        <article wire:key="inv-{{ $invoice->id }}" class="overflow-hidden rounded-xl bg-surface ring-1 ring-steel-200">
            <header class="flex flex-wrap items-center gap-3 border-b border-steel-200 px-5 py-4 sm:px-6">
                <div class="min-w-0 flex-1">
                    <p class="font-mono text-sm font-bold text-ink-950">{{ $invoice->number }}</p>
                    <p class="text-xs text-steel-700"><x-datetime :value="$invoice->issued_at" format="d/m/Y" /> · {{ $invoice->location?->name }} · vence {{ $invoice->due_on?->format('d/m/Y') }}</p>
                </div>
                <x-badge :color="$invoice->isOverdue() ? 'danger' : $invoice->status->color()" dot>{{ $invoice->isOverdue() ? 'En mora' : $invoice->status->label() }}</x-badge>
                <p class="w-28 text-right font-display text-2xl font-bold text-ink-950">{{ $invoice->money($invoice->total_cents)->format() }}</p>
            </header>
            <div class="px-5 py-3 text-sm sm:px-6">
                @foreach ($invoice->items as $item)
                    <div class="flex justify-between gap-4 py-1">
                        <span class="text-ink-950">{{ $item->description }}@if ($item->discount_cents > 0) <span class="text-danger-700">(desc. {{ $invoice->money($item->discount_cents)->format() }})</span>@endif</span>
                        <span class="text-steel-700">{{ $invoice->money($item->total_cents)->format() }}</span>
                    </div>
                @endforeach
                @foreach ($invoice->payments as $payment)
                    <div wire:key="pay-{{ $payment->id }}" class="mt-2 flex flex-wrap items-center justify-between gap-2 rounded-lg bg-canvas px-3 py-2">
                        <span class="text-steel-700">
                            <x-badge :color="$payment->status->color()">{{ $payment->status->label() }}</x-badge>
                            {{ $payment->method->label() }}@if ($payment->reference) · ref. {{ $payment->reference }}@endif
                            · <x-datetime :value="$payment->paid_at" /> · {{ $payment->receiver?->name }}
                        </span>
                        <span class="flex items-center gap-2">
                            <span class="font-semibold text-ink-950">{{ $payment->amount()->format() }}</span>
                            @can('void', $payment)
                                <x-button variant="ghost" size="sm" class="text-danger-700" wire:click="openVoidPayment({{ $payment->id }})">Anular</x-button>
                            @endcan
                        </span>
                    </div>
                @endforeach
            </div>
            <footer class="flex flex-wrap items-center justify-between gap-2 border-t border-steel-200 bg-canvas px-5 py-3 sm:px-6">
                <p class="text-sm text-steel-700">Pagado {{ $invoice->money($invoice->paid_cents)->format() }} · Saldo <span class="font-bold text-ink-950">{{ $invoice->money($invoice->balanceCents())->format() }}</span></p>
                <div class="flex gap-2">
                    <x-button variant="ghost" size="sm" :href="route('admin.invoices.show', $invoice)" target="_blank">Ver comprobante</x-button>
                    @can('void', $invoice)
                        <x-button variant="ghost" size="sm" class="text-danger-700" wire:click="openVoidInvoice({{ $invoice->id }})">Anular</x-button>
                    @endcan
                    @can('pay', $invoice)
                        <x-button size="sm" wire:click="openPay({{ $invoice->id }})">Registrar pago</x-button>
                    @endcan
                </div>
            </footer>
        </article>
    @empty
        <x-card><x-empty-state icon="chart" title="Sin comprobantes" description="Al vender una membresía se emite su comprobante." /></x-card>
    @endforelse

    <x-modal wire:model="showPay" title="Registrar pago" max-width="md">
        <form wire:submit="pay" id="pay-form" class="space-y-4">
            @if ($current)
                <p class="text-sm text-steel-700">Comprobante <span class="font-mono font-bold text-ink-950">{{ $current->number }}</span> · saldo {{ $current->money($current->balanceCents())->format() }}</p>
            @endif
            <x-input label="Valor (pesos)" inputmode="numeric" wire:model="amount" required />
            <x-select label="Medio de pago" wire:model.live="method" :options="$methods" required />
            @if ($method !== 'cash')
                <x-input label="Referencia o voucher" wire:model="reference" />
            @endif
        </form>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="open = false">Cancelar</x-button>
            <x-button type="submit" form="pay-form" wire:loading.attr="disabled">Registrar</x-button>
        </x-slot:footer>
    </x-modal>

    <x-modal wire:model="showVoid" :title="$paymentId ? 'Anular pago' : 'Anular comprobante'" max-width="md">
        <form wire:submit="void" id="void-form" class="space-y-4">
            <p class="text-sm text-steel-700">{{ $paymentId ? 'Úselo si el pago se registró por error. El comprobante volverá a quedar con saldo.' : 'El número del comprobante no se reutiliza.' }}</p>
            <x-input label="Motivo" wire:model="voidReason" required />
            <x-field-error name="invoice" />
            <x-field-error name="payment" />
        </form>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="open = false">Volver</x-button>
            <x-button type="submit" form="void-form" variant="danger">Anular</x-button>
        </x-slot:footer>
    </x-modal>
</div>
