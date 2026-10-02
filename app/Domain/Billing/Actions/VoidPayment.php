<?php

namespace App\Domain\Billing\Actions;

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Models\Payment;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Anula un pago registrado por error (no es un reembolso: el dinero nunca
 * entró). El comprobante vuelve a quedar con saldo.
 */
class VoidPayment
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function execute(Payment $payment, string $reason, User $actor): void
    {
        if ($payment->status !== PaymentStatus::Paid) {
            throw ValidationException::withMessages(['payment' => 'Solo se pueden anular pagos recibidos.']);
        }

        DB::transaction(function () use ($payment, $reason, $actor) {
            $payment->update(['status' => PaymentStatus::Cancelled]);
            $payment->invoice->recalculate();

            $this->audit->log('billing', AuditEvent::PaymentVoided, $payment, $actor, [
                'invoice' => $payment->invoice->number,
                'amount_cents' => $payment->amount_cents,
                'reason' => $reason,
            ]);
        });
    }
}
