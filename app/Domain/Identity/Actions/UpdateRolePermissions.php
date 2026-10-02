<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Enums\RoleName;
use App\Domain\Identity\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Spatie\Permission\Models\Role;

/**
 * Cambia los permisos de un rol. Solo Super Admin; el rol Super Admin no se edita.
 */
class UpdateRolePermissions
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  list<string>  $permissions
     */
    public function execute(Role $role, array $permissions, User $actor): void
    {
        if (! $actor->isSuperAdmin() || $role->name === RoleName::SuperAdmin->value) {
            throw new AuthorizationException('Solo Super Admin puede cambiar permisos.');
        }

        $valid = Permission::values();
        $permissions = array_values(array_unique(array_intersect($permissions, $valid)));
        sort($permissions);

        $old = $role->permissions()->pluck('name')->sort()->values()->all();

        if ($old === $permissions) {
            return;
        }

        $role->syncPermissions($permissions);

        $this->audit->log('roles', AuditEvent::PermissionsUpdated, $role, $actor, [
            'role' => $role->name,
            'added' => array_values(array_diff($permissions, $old)),
            'removed' => array_values(array_diff($old, $permissions)),
        ]);
    }
}
