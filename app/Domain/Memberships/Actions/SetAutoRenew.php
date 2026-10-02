<?php

namespace App\Domain\Memberships\Actions;

use App\Domain\Memberships\Models\Membership;
use Illuminate\Validation\ValidationException;

class SetAutoRenew
{
    public function execute(Membership $membership, bool $autoRenews): void
    {
        if (! $membership->status->isCurrent()) {
            throw ValidationException::withMessages(['autoRenew' => 'La membresía ya no está vigente.']);
        }

        $membership->forceFill(['auto_renews' => $autoRenews])->save();
    }
}
