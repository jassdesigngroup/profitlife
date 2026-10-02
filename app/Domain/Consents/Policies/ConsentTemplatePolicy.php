<?php

namespace App\Domain\Consents\Policies;

use App\Domain\Consents\Models\ConsentTemplate;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;

class ConsentTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ConsentTemplatesManage->value);
    }

    public function view(User $user, ConsentTemplate $template): bool
    {
        // Quien captura consentimientos debe poder leer el texto que firma el cliente.
        return $user->can(Permission::ConsentTemplatesManage->value) || $user->can(Permission::MembersUpdate->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ConsentTemplatesManage->value);
    }

    public function update(User $user, ConsentTemplate $template): bool
    {
        return $user->can(Permission::ConsentTemplatesManage->value);
    }
}
