@php $brand = app(\App\Domain\Settings\Services\Settings::class)->brandName(); @endphp
<!DOCTYPE html>
<html lang="es" class="bg-canvas">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Comprobante {{ $invoice->number }} · {{ $brand }}</title>
    @fonts
    @vite(['resources/css/app.css'])
    <style>@media print { .no-print { display: none !important } body { background: #fff } .sheet { box-shadow: none !important; margin: 0 !important } }</style>
</head>
<body class="py-8 text-ink-950">
    <div class="no-print mx-auto mb-4 flex max-w-3xl justify-end gap-2 px-4">
        <button type="button" onclick="window.print()" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-bold text-white hover:bg-brand-600">Imprimir</button>
    </div>
    <main class="sheet mx-auto max-w-3xl bg-surface p-8 shadow-xl sm:p-12">
        <header class="flex items-start justify-between gap-6 border-b border-steel-200 pb-6">
            <div>
                <x-brand />
                <p class="mt-3 text-sm text-steel-700">{{ $invoice->location->name }}<br>{{ $invoice->location->address_line }}, {{ $invoice->location->city }}<br>{{ $invoice->location->phone }}</p>
            </div>
            <div class="text-right">
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-brand-700">Comprobante de pago</p>
                <p class="font-mono text-2xl font-bold">{{ $invoice->number }}</p>
                <p class="mt-1 text-sm text-steel-700">Emitido <x-datetime :value="$invoice->issued_at" format="d/m/Y" /></p>
                <p class="mt-2"><x-badge :color="$invoice->status->color()">{{ $invoice->status->label() }}</x-badge></p>
            </div>
        </header>

        <section class="mt-6 text-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-steel-700">Cliente</p>
            <p class="mt-1 font-semibold">{{ $invoice->member->full_name }} · <span class="font-mono">{{ $invoice->member->member_number }}</span></p>
            <p class="text-steel-700">{{ $invoice->member->document_type?->value }} {{ $invoice->member->document_number }}</p>
        </section>

        <table class="mt-6 w-full text-sm">
            <thead><tr class="border-b border-steel-200 text-left text-xs uppercase tracking-wider text-steel-700"><th class="py-2">Concepto</th><th class="py-2 text-right">Valor</th><th class="py-2 text-right">Desc.</th><th class="py-2 text-right">Total</th></tr></thead>
            <tbody>
                @foreach ($invoice->items as $item)
                    <tr class="border-b border-steel-100"><td class="py-2">{{ $item->description }}@if ($item->quantity > 1) × {{ $item->quantity }}@endif</td><td class="py-2 text-right">{{ $invoice->money($item->unit_price_cents * $item->quantity)->format() }}</td><td class="py-2 text-right">{{ $item->discount_cents ? $invoice->money($item->discount_cents)->format() : '—' }}</td><td class="py-2 text-right font-semibold">{{ $invoice->money($item->total_cents)->format() }}</td></tr>
                @endforeach
            </tbody>
        </table>

        <dl class="ml-auto mt-4 w-64 space-y-1 text-sm">
            @if ($invoice->tax_cents > 0)<div class="flex justify-between text-steel-700"><dt>IVA incluido</dt><dd>{{ $invoice->money($invoice->tax_cents)->format() }}</dd></div>@endif
            <div class="flex justify-between text-base font-bold"><dt>Total</dt><dd>{{ $invoice->money($invoice->total_cents)->format() }}</dd></div>
            <div class="flex justify-between"><dt>Pagado</dt><dd>{{ $invoice->money($invoice->paid_cents)->format() }}</dd></div>
            <div class="flex justify-between font-semibold"><dt>Saldo</dt><dd>{{ $invoice->money($invoice->balanceCents())->format() }}</dd></div>
        </dl>

        @if ($invoice->payments->isNotEmpty())
            <section class="mt-6 text-sm">
                <p class="text-xs font-bold uppercase tracking-wider text-steel-700">Pagos</p>
                @foreach ($invoice->payments as $payment)
                    <p class="mt-1">{{ $payment->paid_at?->setTimezone(app(\App\Domain\Settings\Services\Settings::class)->displayTimezone())->format('d/m/Y H:i') }} · {{ $payment->method->label() }} · {{ $payment->amount()->format() }} · {{ $payment->status->label() }}</p>
                @endforeach
            </section>
        @endif

        @if ($invoice->notes)<p class="mt-6 whitespace-pre-line text-xs text-steel-700">{{ $invoice->notes }}</p>@endif

        <footer class="mt-10 border-t border-steel-200 pt-4 text-xs text-steel-700">
            Documento interno de soporte de pago. <strong>No es una factura electrónica de venta.</strong>
        </footer>
    </main>
</body>
</html>
