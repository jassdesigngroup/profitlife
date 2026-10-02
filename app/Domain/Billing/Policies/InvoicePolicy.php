<?php

namespace App\Domain\Billing\Policies;

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;

/**
 * Los comprobantes pertenecen a la sede que los emite.
 */
class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::PaymentsView->value);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->can(Permission::PaymentsView->value) && $user->canAccessLocation($invoice->location_id);
    }

    public function pay(User $user, Invoice $invoice): bool
    {
        return $user->can(Permission::PaymentsCreate->value)
            && $user->canAccessLocation($invoice->location_id)
            && $invoice->status->isOpen();
    }

    public function void(User $user, Invoice $invoice): bool
    {
        return $user->can(Permission::PaymentsVoid->value)
            && $user->canAccessLocation($invoice->location_id)
            && $invoice->status !== InvoiceStatus::Void
            && $invoice->paid_cents === 0;
    }
}
