<?php

namespace App\Domain\CheckIns\Policies;

use App\Domain\CheckIns\Enums\CheckInResult;
use App\Domain\CheckIns\Models\CheckIn;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Locations\Models\Location;
use App\Domain\Members\Models\Member;

class CheckInPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::CheckInsView->value);
    }

    /**
     * Registrar un ingreso manual de un cliente en una sede.
     */
    public function create(User $user, Location $location, ?Member $member = null): bool
    {
        return $user->can(Permission::CheckInsCreate->value)
            && $user->canAccessLocation($location->id)
            && ($member === null || $user->can('view', $member));
    }

    /**
     * Autorizar un ingreso que fue rechazado.
     */
    public function override(User $user, CheckIn $checkIn): bool
    {
        return $user->can(Permission::CheckInsOverride->value)
            && $user->canAccessLocation($checkIn->location_id)
            && $checkIn->result === CheckInResult::Rejected
            && $checkIn->member_id !== null
            && $checkIn->rejection_reason?->canBeOverridden() === true;
    }
}
