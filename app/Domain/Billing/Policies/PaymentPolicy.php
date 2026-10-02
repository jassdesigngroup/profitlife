<?php

namespace App\Domain\Billing\Policies;

use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Models\Payment;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::PaymentsView->value);
    }

    public function void(User $user, Payment $payment): bool
    {
        return $user->can(Permission::PaymentsVoid->value)
            && $user->canAccessLocation($payment->location_id)
            && $payment->status === PaymentStatus::Paid;
    }
}
