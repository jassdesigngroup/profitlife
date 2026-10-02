<?php

namespace App\Domain\Memberships\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\Membership;
use App\Domain\Memberships\Services\MembershipStatusChanger;
use Illuminate\Validation\ValidationException;

/**
 * Suspensión o reactivación manual (por ejemplo, por una falta o al
 * resolver un caso). Congelar y cancelar tienen sus propias acciones.
 */
class ChangeMembershipStatus
{
    public function __construct(private readonly MembershipStatusChanger $statuses) {}

    public function execute(Membership $membership, MembershipStatus $to, string $reason, User $actor): void
    {
        $allowed = [
            MembershipStatus::Active->value => [MembershipStatus::Suspended],
            MembershipStatus::Suspended->value => [MembershipStatus::Active],
        ];

        if (! in_array($to, $allowed[$membership->status->value] ?? [], true)) {
            throw ValidationException::withMessages(['status' => 'Ese cambio de estado no está permitido.']);
        }

        $this->statuses->change($membership, $to, $reason, $actor);
    }
}
