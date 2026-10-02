<?php

namespace App\Domain\Staff\Policies;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Enums\RoleName;
use App\Domain\Identity\Models\User;
use App\Domain\Staff\Models\Staff;

/**
 * Un usuario gestiona a un empleado si comparte al menos una sede con él
 * (o ve todas) y si todos los roles del empleado están entre los que el
 * usuario puede asignar. Así un Gerente no puede tocar a un Administrador
 * ni nadie distinto de un Super Admin a otro Super Admin.
 */
class StaffPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::StaffView->value);
    }

    public function view(User $user, Staff $staff): bool
    {
        return $user->can(Permission::StaffView->value) && $this->inScope($user, $staff);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::StaffCreate->value);
    }

    public function update(User $user, Staff $staff): bool
    {
        return $user->can(Permission::StaffUpdate->value)
            && $this->inScope($user, $staff)
            && $this->canManage($user, $staff);
    }

    public function toggleStatus(User $user, Staff $staff): bool
    {
        return $user->can(Permission::StaffToggleStatus->value)
            && $staff->user_id !== $user->id
            && $this->inScope($user, $staff)
            && $this->canManage($user, $staff);
    }

    public function resendInvitation(User $user, Staff $staff): bool
    {
        return $user->can(Permission::StaffInvite->value)
            && ! $staff->user->hasAcceptedInvitation()
            && $this->inScope($user, $staff)
            && $this->canManage($user, $staff);
    }

    public function manageRoles(User $user, Staff $staff): bool
    {
        return $user->can(Permission::UsersManageRoles->value)
            && $staff->user_id !== $user->id
            && $this->inScope($user, $staff)
            && $this->canManage($user, $staff);
    }

    private function inScope(User $user, Staff $staff): bool
    {
        return $user->canAccessAnyLocation($staff->locationIds());
    }

    private function canManage(User $user, Staff $staff): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $assignable = array_map(fn (RoleName $r) => $r->value, RoleName::assignableBy($user));

        return $staff->user->getRoleNames()->every(fn (string $role) => in_array($role, $assignable, true));
    }
}
