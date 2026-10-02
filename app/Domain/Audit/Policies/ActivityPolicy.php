<?php

namespace App\Domain\Audit\Policies;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use Spatie\Activitylog\Models\Activity;

/**
 * La auditoría es de solo lectura: no hay reglas de edición ni borrado.
 */
class ActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::AuditView->value);
    }

    public function view(User $user, Activity $activity): bool
    {
        return $user->can(Permission::AuditView->value);
    }
}
