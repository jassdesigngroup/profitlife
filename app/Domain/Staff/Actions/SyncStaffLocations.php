<?php

namespace App\Domain\Staff\Actions;

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Models\User;
use App\Domain\Locations\Models\Location;
use App\Domain\Staff\Models\Staff;
use App\Support\Scopes\LocationScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Asigna las sedes de un empleado. Un actor sin acceso a todas las sedes
 * solo puede añadir o quitar sedes de su alcance; las demás asignaciones
 * del empleado se conservan intactas.
 */
class SyncStaffLocations
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  list<int>  $locationIds
     */
    public function execute(Staff $staff, array $locationIds, ?int $primaryLocationId, User $actor): void
    {
        $requested = array_values(array_unique(array_map('intval', $locationIds)));

        $existing = Location::query()->withoutGlobalScope(LocationScope::class)->whereIn('id', $requested)->pluck('id')->all();
        if (count($existing) !== count($requested)) {
            throw ValidationException::withMessages(['locationIds' => 'Alguna de las sedes no existe.']);
        }

        $current = $staff->locationIds();

        if (! $actor->canAccessAllLocations()) {
            $mine = $actor->accessibleLocationIds();

            foreach (array_merge(array_diff($requested, $current), array_diff($current, $requested)) as $changed) {
                if (! in_array($changed, $mine, true)) {
                    // Sedes ajenas que el empleado ya tenía se conservan; no se pueden añadir nuevas.
                    if (in_array($changed, $current, true)) {
                        $requested[] = $changed;

                        continue;
                    }

                    throw new AuthorizationException('No puede asignar una sede fuera de su alcance.');
                }
            }
        }

        $requested = array_values(array_unique($requested));

        if ($requested === []) {
            throw ValidationException::withMessages(['locationIds' => 'Asigne al menos una sede.']);
        }

        if ($primaryLocationId === null || ! in_array($primaryLocationId, $requested, true)) {
            $primaryLocationId = $requested[0];
        }

        $sync = [];
        foreach ($requested as $id) {
            $sync[$id] = ['is_primary' => $id === $primaryLocationId];
        }

        $staff->locations()->withoutGlobalScope(LocationScope::class)->sync($sync);

        sort($current);
        sort($requested);

        if ($current !== $requested) {
            $this->audit->log('staff', AuditEvent::LocationsAssigned, $staff, $actor, [
                'old' => $current,
                'attributes' => $requested,
            ]);
        }
    }
}
