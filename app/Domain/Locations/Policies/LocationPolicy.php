<?php

namespace App\Domain\Locations\Policies;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Locations\Models\Location;

/**
 * Cada regla exige el permiso y, además, que la sede esté en el alcance
 * del usuario. El global scope no sustituye esta comprobación.
 */
class LocationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::LocationsView->value);
    }

    public function view(User $user, Location $location): bool
    {
        return $user->can(Permission::LocationsView->value) && $user->canAccessLocation($location->id);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::LocationsCreate->value);
    }

    public function update(User $user, Location $location): bool
    {
        return $user->can(Permission::LocationsUpdate->value) && $user->canAccessLocation($location->id);
    }

    public function toggleStatus(User $user, Location $location): bool
    {
        return $user->can(Permission::LocationsToggleStatus->value) && $user->canAccessLocation($location->id);
    }

    public function manageHours(User $user, Location $location): bool
    {
        return $user->can(Permission::LocationsManageHours->value) && $user->canAccessLocation($location->id);
    }

    public function manageClosures(User $user, Location $location): bool
    {
        return $user->can(Permission::LocationsManageClosures->value) && $user->canAccessLocation($location->id);
    }

    /**
     * Los cierres sin sede afectan a todas: solo quien ve todas las sedes.
     */
    public function manageGlobalClosures(User $user): bool
    {
        return $user->can(Permission::LocationsManageClosures->value) && $user->canAccessAllLocations();
    }
}
