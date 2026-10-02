<?php

namespace App\Domain\Members\Policies;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Members\Models\Member;

/**
 * Permiso + alcance por la sede principal del cliente. Los documentos
 * clínicos exigen además permisos clínicos (Recepción y Gerente no los tienen).
 */
class MemberPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::MembersView->value);
    }

    public function view(User $user, Member $member): bool
    {
        return $user->can(Permission::MembersView->value) && $user->canAccessAnyLocation($member->locationIds());
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::MembersCreate->value);
    }

    public function update(User $user, Member $member): bool
    {
        return $user->can(Permission::MembersUpdate->value) && $user->canAccessAnyLocation($member->locationIds());
    }

    public function delete(User $user, Member $member): bool
    {
        return $user->can(Permission::MembersDelete->value) && $user->canAccessAnyLocation($member->locationIds());
    }

    public function viewClinicalDocuments(User $user, Member $member): bool
    {
        return $user->can(Permission::ClinicalNotesView->value) && $this->view($user, $member);
    }

    public function uploadClinicalDocuments(User $user, Member $member): bool
    {
        return $user->can(Permission::ClinicalNotesCreate->value) && $this->view($user, $member);
    }
}
