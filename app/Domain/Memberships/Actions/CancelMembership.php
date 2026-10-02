<?php

namespace App\Domain\Memberships\Actions;

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Billing\Actions\VoidInvoice;
use App\Domain\Identity\Models\User;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\Membership;
use App\Domain\Memberships\Services\MembershipStatusChanger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cancela una membresía vigente. Si su comprobante no tiene pagos, se anula;
 * si tiene pagos, se conserva (la devolución llega con la Fase 10).
 */
class CancelMembership
{
    public function __construct(
        private readonly MembershipStatusChanger $statuses,
        private readonly VoidInvoice $voidInvoice,
        private readonly AuditLogger $audit,
    ) {}

    public function execute(Membership $membership, string $reason, User $actor): void
    {
        if (! $membership->status->isCurrent()) {
            throw ValidationException::withMessages(['cancel' => 'La membresía ya no está vigente.']);
        }

        DB::transaction(function () use ($membership, $reason, $actor) {
            $membership->forceFill([
                'cancelled_at' => now(),
                'cancellation_reason' => mb_substr($reason, 0, 255),
                'auto_renews' => false,
            ])->save();

            $this->statuses->change($membership, MembershipStatus::Cancelled, $reason, $actor);

            $invoice = $membership->invoice();
            if ($invoice !== null && $invoice->paid_cents === 0) {
                $this->voidInvoice->execute($invoice, 'Membresía cancelada', $actor);
            }

            $this->audit->log('memberships', AuditEvent::MembershipCancelled, $membership, $actor, ['reason' => $reason]);
        });
    }
}
