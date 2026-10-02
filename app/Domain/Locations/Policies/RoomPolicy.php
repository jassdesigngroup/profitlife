<?php

namespace App\Domain\Locations\Policies;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Locations\Models\Location;
use App\Domain\Locations\Models\Room;

class RoomPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::RoomsView->value);
    }

    public function view(User $user, Room $room): bool
    {
        return $user->can(Permission::RoomsView->value) && $user->canAccessLocation($room->location_id);
    }

    public function create(User $user, ?Location $location = null): bool
    {
        if (! $user->can(Permission::RoomsCreate->value)) {
            return false;
        }

        return $location === null || $user->canAccessLocation($location->id);
    }

    public function update(User $user, Room $room): bool
    {
        return $user->can(Permission::RoomsUpdate->value) && $user->canAccessLocation($room->location_id);
    }

    public function delete(User $user, Room $room): bool
    {
        return $user->can(Permission::RoomsDelete->value) && $user->canAccessLocation($room->location_id);
    }
}
