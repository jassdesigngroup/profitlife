<?php

namespace App\Domain\Billing\Actions;

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Events\InvoiceSettled;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Payment;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registra un pago manual (total o abono) contra un comprobante abierto.
 */
class RecordPayment
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function execute(Invoice $invoice, int $amountCents, PaymentMethod $method, ?string $reference, User $actor): Payment
    {
        if ($method === PaymentMethod::Gateway) {
            throw ValidationException::withMessages(['method' => 'Los pagos en línea se registran desde la pasarela.']);
        }

        $payment = DB::transaction(function () use ($invoice, $amountCents, $method, $reference, $actor) {
            $invoice = Invoice::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($invoice->id);

            if (! $invoice->status->isOpen()) {
                throw ValidationException::withMessages(['amount' => 'El comprobante no tiene saldo pendiente.']);
            }

            if ($amountCents <= 0 || $amountCents > $invoice->balanceCents()) {
                throw ValidationException::withMessages(['amount' => 'El valor debe ser mayor que cero y no superar el saldo de '.$invoice->money($invoice->balanceCents())->format().'.']);
            }

            $payment = Payment::query()->create([
                'invoice_id' => $invoice->id,
                'member_id' => $invoice->member_id,
                'location_id' => $invoice->location_id,
                'amount_cents' => $amountCents,
                'currency' => $invoice->currency,
                'method' => $method,
                'status' => PaymentStatus::Paid,
                'reference' => $reference,
                'paid_at' => now(),
                'received_by' => $actor->id,
            ]);

            $invoice->recalculate();

            $this->audit->log('billing', AuditEvent::PaymentRecorded, $payment, $actor, [
                'invoice' => $invoice->number,
                'amount_cents' => $amountCents,
                'method' => $method->value,
            ]);

            return $payment;
        });

        $invoice = $payment->invoice;
        if ($invoice->status === InvoiceStatus::Paid) {
            InvoiceSettled::dispatch($invoice);
        }

        return $payment;
    }
}
