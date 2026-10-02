<?php

namespace App\Domain\Members\Policies;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Members\Models\Member;
use App\Domain\Physiotherapy\Services\ClinicalAccess;

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
        // Con historia clínica, solo el equipo tratante (o un acceso de emergencia).
        return $user->can(Permission::ClinicalNotesView->value)
            && app(ClinicalAccess::class)->canViewClinicalDocuments($user, $member);
    }

    public function uploadClinicalDocuments(User $user, Member $member): bool
    {
        return $user->can(Permission::ClinicalNotesCreate->value)
            && $this->view($user, $member)
            && app(ClinicalAccess::class)->canViewClinicalDocuments($user, $member);
    }
}
