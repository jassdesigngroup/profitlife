<?php

namespace App\Domain\Staff\Actions;

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Enums\RoleName;
use App\Domain\Identity\Models\User;
use App\Domain\Staff\Models\Staff;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Asigna los roles de un empleado. Solo se pueden asignar o quitar roles
 * que el actor puede gestionar (RoleName::assignableBy): nadie salvo un
 * Super Admin asigna o retira el rol Super Admin.
 */
class SyncStaffRoles
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  list<RoleName>  $roles
     */
    public function execute(Staff $staff, array $roles, User $actor): void
    {
        $user = $staff->user;
        $assignable = array_map(fn (RoleName $r) => $r->value, RoleName::assignableBy($actor));
        $staffRoles = array_map(fn (RoleName $r) => $r->value, RoleName::staffRoles());
        $allCurrent = $user->getRoleNames()->all();

        // Solo se gestionan roles de staff; los demás (p. ej. Cliente) se conservan.
        $current = array_values(array_intersect($allCurrent, $staffRoles));
        $kept = array_values(array_diff($allCurrent, $staffRoles));
        $requested = array_values(array_unique(array_map(fn (RoleName $r) => $r->value, $roles)));

        if (array_diff($requested, $staffRoles) !== []) {
            throw new AuthorizationException('Solo se pueden asignar roles de staff.');
        }

        $added = array_values(array_diff($requested, $current));
        $removed = array_values(array_diff($current, $requested));

        foreach ([...$added, ...$removed] as $role) {
            if (! in_array($role, $assignable, true)) {
                throw new AuthorizationException('No puede asignar ni retirar el rol '.(RoleName::tryFrom($role)?->label() ?? $role).'.');
            }
        }

        if ($added === [] && $removed === []) {
            return;
        }

        if ($user->is($actor)) {
            throw new AuthorizationException('No puede cambiar sus propios roles.');
        }

        $user->syncRoles([...$requested, ...$kept]);

        $this->audit->log('roles', AuditEvent::RolesUpdated, $user, $actor, [
            'old' => $current,
            'attributes' => $requested,
        ]);
    }
}
