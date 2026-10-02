<?php

namespace App\Domain\CheckIns\Policies;

use App\Domain\CheckIns\Models\KioskDevice;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Locations\Models\Location;

/**
 * Los kioscos se gestionan desde la sede, con el permiso de editarla.
 */
class KioskDevicePolicy
{
    public function viewAny(User $user, Location $location): bool
    {
        return $user->can(Permission::LocationsView->value) && $user->canAccessLocation($location->id);
    }

    public function create(User $user, Location $location): bool
    {
        return $user->can(Permission::LocationsUpdate->value) && $user->canAccessLocation($location->id);
    }

    public function update(User $user, KioskDevice $device): bool
    {
        return $user->can(Permission::LocationsUpdate->value) && $user->canAccessLocation($device->location_id);
    }
}
