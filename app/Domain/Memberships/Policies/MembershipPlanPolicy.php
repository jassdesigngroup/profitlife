<?php

namespace App\Domain\Memberships\Policies;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Memberships\Models\MembershipPlan;

class MembershipPlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::MembershipsManagePlans->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::MembershipsManagePlans->value);
    }

    public function update(User $user, MembershipPlan $plan): bool
    {
        return $user->can(Permission::MembershipsManagePlans->value);
    }
}
