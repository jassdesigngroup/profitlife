<?php

namespace App\Livewire\Admin\Members;

use App\Domain\Billing\Actions\RecordPayment;
use App\Domain\Billing\Actions\VoidInvoice;
use App\Domain\Billing\Actions\VoidPayment;
use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Payment;
use App\Livewire\Admin\Concerns\InteractsWithToasts;
use App\Livewire\Admin\Concerns\ParsesMoney;
use App\Livewire\Admin\Members\Concerns\ResolvesMember;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Comprobantes y pagos del cliente. Solo se ven los comprobantes de las
 * sedes del usuario (LocationScope sobre `invoices.location_id`).
 */
class MemberBilling extends Component
{
    use InteractsWithToasts, ParsesMoney, ResolvesMember;

    #[Locked]
    public ?int $invoiceId = null;

    #[Locked]
    public ?int $paymentId = null;

    public bool $showPay = false;

    public string $amount = '';

    public string $method = 'cash';

    public string $reference = '';

    public bool $showVoid = false;

    public string $voidReason = '';

    #[On('member-billing-changed')]
    public function refreshBilling(): void
    {
        // Solo vuelve a renderizar.
    }

    public function openPay(int $invoiceId): void
    {
        $invoice = $this->invoice($invoiceId);
        $this->authorize('pay', $invoice);

        $this->invoiceId = $invoice->id;
        $this->amount = $this->centsToPesos($invoice->balanceCents());
        $this->method = PaymentMethod::Cash->value;
        $this->reference = '';
        $this->resetValidation();
        $this->showPay = true;
    }

    public function pay(RecordPayment $record): void
    {
        $invoice = $this->invoice((int) $this->invoiceId);
        $this->authorize('pay', $invoice);

        $this->validate([
            'amount' => ['required', 'regex:/^[\d.,\s]+$/'],
            'method' => ['required', Rule::in(array_keys(PaymentMethod::manualOptions()))],
            'reference' => ['nullable', 'string', 'max:100'],
        ], [], ['amount' => 'valor', 'method' => 'medio de pago', 'reference' => 'referencia']);

        $record->execute($invoice, $this->pesosToCents($this->amount), PaymentMethod::from($this->method), $this->reference ?: null, auth()->user());

        $this->showPay = false;
        $this->toast('Pago registrado.');
    }

    public function openVoidInvoice(int $invoiceId): void
    {
        $invoice = $this->invoice($invoiceId);
        $this->authorize('void', $invoice);
        $this->invoiceId = $invoice->id;
        $this->paymentId = null;
        $this->voidReason = '';
        $this->resetValidation();
        $this->showVoid = true;
    }

    public function openVoidPayment(int $paymentId): void
    {
        $payment = $this->payment($paymentId);
        $this->authorize('void', $payment);
        $this->paymentId = $payment->id;
        $this->invoiceId = null;
        $this->voidReason = '';
        $this->resetValidation();
        $this->showVoid = true;
    }

    public function void(VoidInvoice $voidInvoice, VoidPayment $voidPayment): void
    {
        $this->validate(['voidReason' => ['required', 'string', 'max:200']], [], ['voidReason' => 'motivo']);

        if ($this->paymentId !== null) {
            $payment = $this->payment($this->paymentId);
            $this->authorize('void', $payment);
            $voidPayment->execute($payment, $this->voidReason, auth()->user());
            $this->toast('Pago anulado.', 'warning');
        } else {
            $invoice = $this->invoice((int) $this->invoiceId);
            $this->authorize('void', $invoice);
            $voidInvoice->execute($invoice, $this->voidReason, auth()->user());
            $this->toast('Comprobante anulado.', 'warning');
        }

        $this->showVoid = false;
    }

    public function render(): View
    {
        $member = $this->member();
        $invoices = Invoice::query()->where('member_id', $member->id)
            ->with(['items', 'payments.receiver:id,name', 'location:id,name'])
            ->latest('id')
            ->get();

        return view('livewire.admin.members.billing', [
            'member' => $member,
            'invoices' => $invoices,
            'balance' => $invoices->sum(fn (Invoice $i) => $i->balanceCents()),
            'methods' => PaymentMethod::manualOptions(),
            'current' => $this->invoiceId ? $invoices->firstWhere('id', $this->invoiceId) : null,
        ]);
    }

    private function invoice(int $invoiceId): Invoice
    {
        // LocationScope + pertenencia al cliente.
        return Invoice::query()->where('member_id', $this->member()->id)->findOrFail($invoiceId);
    }

    private function payment(int $paymentId): Payment
    {
        return Payment::query()->where('member_id', $this->member()->id)->findOrFail($paymentId);
    }
}
