<?php

namespace App\Domain\Identity\Policies;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Enums\RoleName;
use App\Domain\Identity\Models\User;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::RolesView->value);
    }

    public function view(User $user, Role $role): bool
    {
        return $user->can(Permission::RolesView->value);
    }

    /**
     * Solo Super Admin cambia permisos. El rol Super Admin siempre conserva
     * todos los permisos y no se edita.
     */
    public function updatePermissions(User $user, Role $role): bool
    {
        return $user->isSuperAdmin()
            && $user->can(Permission::UsersManagePermissions->value)
            && $role->name !== RoleName::SuperAdmin->value;
    }
}
