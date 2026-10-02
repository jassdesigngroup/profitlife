<?php

namespace App\Domain\Billing\Actions;

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Identity\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Anula un comprobante sin pagos. El número no se reutiliza.
 */
class VoidInvoice
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function execute(Invoice $invoice, string $reason, ?User $actor): void
    {
        if ($invoice->paid_cents > 0) {
            throw ValidationException::withMessages(['invoice' => 'Anule primero los pagos del comprobante.']);
        }

        if ($invoice->status === InvoiceStatus::Void) {
            return;
        }

        $invoice->forceFill([
            'status' => InvoiceStatus::Void,
            'notes' => trim(($invoice->notes ? $invoice->notes."\n" : '').'Anulado: '.$reason),
        ])->save();

        $this->audit->log('billing', AuditEvent::InvoiceVoided, $invoice, $actor, [
            'number' => $invoice->number,
            'reason' => $reason,
        ]);
    }
}
