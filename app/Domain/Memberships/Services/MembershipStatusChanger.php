<?php

namespace App\Domain\Memberships\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\Membership;

/**
 * Único punto que cambia el estado de una membresía: deja siempre la fila
 * en `membership_status_histories` (actor nulo = tarea automática).
 */
class MembershipStatusChanger
{
    public function change(Membership $membership, MembershipStatus $to, ?string $reason, ?User $actor): void
    {
        $from = $membership->status;

        if ($from === $to) {
            return;
        }

        $membership->forceFill(['status' => $to])->save();

        $membership->statusHistories()->create([
            'from_status' => $from,
            'to_status' => $to,
            'reason' => $reason === null ? null : mb_substr($reason, 0, 255),
            'changed_by' => $actor?->id,
        ]);
    }

    public function record(Membership $membership, ?string $reason, ?User $actor): void
    {
        $membership->statusHistories()->create([
            'from_status' => null,
            'to_status' => $membership->status,
            'reason' => $reason,
            'changed_by' => $actor?->id,
        ]);
    }
}
