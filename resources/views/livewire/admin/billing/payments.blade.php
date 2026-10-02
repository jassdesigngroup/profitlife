<div>
    <x-page-header eyebrow="Operación" title="Pagos" description="Caja: pagos recibidos por sede y medio de pago." />

    <x-card :padding="false" class="mb-6">
        <div class="grid gap-3 p-4 sm:grid-cols-3 sm:px-6">
            <x-input label="Desde" type="date" wire:model.live="from" />
            <x-input label="Hasta" type="date" wire:model.live="to" />
            <x-select label="Medio de pago" wire:model.live="method" :options="$methods" placeholder="Todos" />
        </div>
    </x-card>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl bg-ink-950 p-5 text-white">
            <p class="text-xs font-bold uppercase tracking-wider text-brand-500">Total recibido</p>
            <p class="mt-1 font-display text-4xl font-bold">{{ $grandTotal->format() }}</p>
        </div>
        @foreach ($totals as $row)
            <div class="rounded-xl bg-surface p-5 ring-1 ring-steel-200">
                <p class="text-xs font-bold uppercase tracking-wider text-steel-700">{{ $row->method->label() }} · {{ $row->count }}</p>
                <p class="mt-1 font-display text-3xl font-bold text-ink-950">{{ \App\Support\Money::ofCents((int) $row->total)->format() }}</p>
            </div>
        @endforeach
    </div>

    <x-card :padding="false">
        @if ($payments->isEmpty())
            <x-empty-state icon="chart" title="Sin pagos en el periodo" />
        @else
            <x-table>
                <x-slot:head>
                    <th scope="col" class="px-6 py-3">Fecha</th>
                    <th scope="col" class="px-4 py-3">Cliente</th>
                    <th scope="col" class="px-4 py-3">Comprobante</th>
                    <th scope="col" class="px-4 py-3">Medio</th>
                    <th scope="col" class="px-4 py-3">Recibió</th>
                    <th scope="col" class="px-6 py-3 text-right">Valor</th>
                </x-slot:head>
                @foreach ($payments as $payment)
                    <tr wire:key="p-{{ $payment->id }}">
                        <td class="whitespace-nowrap px-6 py-3 text-steel-700"><x-datetime :value="$payment->paid_at" /></td>
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.members.show', ['member' => $payment->member_id, 'tab' => 'billing']) }}" wire:navigate class="font-semibold text-ink-950 hover:text-brand-700">{{ $payment->member?->full_name }}</a>
                            <p class="font-mono text-xs text-steel-700">{{ $payment->member?->member_number }}</p>
                        </td>
                        <td class="px-4 py-3"><a href="{{ route('admin.invoices.show', $payment->invoice_id) }}" target="_blank" class="font-mono text-brand-700 hover:underline">{{ $payment->invoice?->number }}</a></td>
                        <td class="px-4 py-3 text-steel-700">{{ $payment->method->label() }}@if ($payment->reference)<p class="text-xs">ref. {{ $payment->reference }}</p>@endif</td>
                        <td class="px-4 py-3 text-steel-700">{{ $payment->receiver?->name }}<p class="text-xs">{{ $payment->location?->name }}</p></td>
                        <td class="px-6 py-3 text-right font-semibold text-ink-950">{{ $payment->amount()->format() }}</td>
                    </tr>
                @endforeach
            </x-table>
            @if ($payments->hasPages())
                <div class="border-t border-steel-200 px-6 py-3">{{ $payments->links() }}</div>
            @endif
        @endif
    </x-card>
</div>
