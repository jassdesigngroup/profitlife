<?php

namespace App\Domain\Members\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Locations\Models\Location;
use App\Support\Scopes\LocationScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * La sede principal de un cliente debe existir, estar activa y pertenecer
 * al alcance de quien la asigna.
 */
class EnsureLocationInScope
{
    public function execute(int $locationId, User $actor): Location
    {
        $location = Location::query()->withoutGlobalScope(LocationScope::class)->find($locationId);

        if ($location === null || ! $location->is_active) {
            throw ValidationException::withMessages(['home_location_id' => 'La sede no existe o está inactiva.']);
        }

        if (! $actor->canAccessLocation($location->id)) {
            throw new AuthorizationException('No puede asignar clientes a una sede fuera de su alcance.');
        }

        return $location;
    }
}
