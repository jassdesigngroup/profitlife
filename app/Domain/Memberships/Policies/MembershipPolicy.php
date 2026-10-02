<?php

namespace App\Domain\Memberships\Policies;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Members\Models\Member;
use App\Domain\Memberships\Models\Membership;

/**
 * Permiso + que el cliente esté en el alcance del usuario.
 */
class MembershipPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::MembershipsView->value);
    }

    public function view(User $user, Membership $membership): bool
    {
        return $user->can(Permission::MembershipsView->value) && $this->inScope($user, $membership);
    }

    /**
     * Vender una membresía a un cliente.
     */
    public function create(User $user, ?Member $member = null): bool
    {
        return $user->can(Permission::MembershipsCreate->value)
            && ($member === null || $user->canAccessAnyLocation($member->locationIds()));
    }

    public function update(User $user, Membership $membership): bool
    {
        return $user->can(Permission::MembershipsUpdate->value) && $this->inScope($user, $membership);
    }

    public function freeze(User $user, Membership $membership): bool
    {
        return $user->can(Permission::MembershipsFreeze->value) && $this->inScope($user, $membership);
    }

    public function cancel(User $user, Membership $membership): bool
    {
        return $user->can(Permission::MembershipsCancel->value) && $this->inScope($user, $membership);
    }

    private function inScope(User $user, Membership $membership): bool
    {
        return $user->canAccessAnyLocation($membership->locationIds());
    }
}
