<?php

namespace App\Domain\Memberships\Listeners;

use App\Domain\Billing\Events\InvoiceSettled;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\Membership;
use App\Domain\Memberships\Services\MembershipStatusChanger;
use App\Support\BusinessDate;

/**
 * Al pagarse el comprobante, la membresía suspendida por falta de pago
 * (suspensión automática) vuelve a quedar activa. Una suspensión manual se
 * respeta.
 */
class ReactivateMembershipOnPayment
{
    public function __construct(private readonly MembershipStatusChanger $statuses) {}

    public function handle(InvoiceSettled $event): void
    {
        $ids = $event->invoice->items()->where('billable_type', 'membership')->pluck('billable_id');

        Membership::query()->withoutGlobalScopes()->whereIn('id', $ids)
            ->where('status', MembershipStatus::Suspended)
            ->get()
            ->each(function (Membership $membership) {
                $last = $membership->statusHistories()->first();
                $autoSuspended = $last !== null && $last->to_status === MembershipStatus::Suspended && $last->changed_by === null;

                if ($autoSuspended && ($membership->ends_on === null || $membership->ends_on->greaterThanOrEqualTo(BusinessDate::today()))) {
                    $this->statuses->change($membership, MembershipStatus::Active, 'Pago recibido', null);
                }
            });
    }
}
