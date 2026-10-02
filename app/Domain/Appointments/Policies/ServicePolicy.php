<?php

namespace App\Domain\Appointments\Policies;

use App\Domain\Appointments\Models\Service;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;

class ServicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ServicesManage->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ServicesManage->value);
    }

    public function update(User $user, Service $service): bool
    {
        return $user->can(Permission::ServicesManage->value);
    }
}
