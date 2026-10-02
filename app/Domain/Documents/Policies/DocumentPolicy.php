<?php

namespace App\Domain\Documents\Policies;

use App\Domain\Documents\Models\Document;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Support\Scopes\LocationScope;

/**
 * La descarga se autoriza contra el cliente dueño del documento. Un
 * documento clínico exige permisos clínicos además del acceso al cliente.
 */
class DocumentPolicy
{
    public function view(User $user, Document $document): bool
    {
        $member = $document->member()->withoutGlobalScope(LocationScope::class)->first();

        if ($member === null) {
            return false;
        }

        return $document->isClinical()
            ? $user->can('viewClinicalDocuments', $member)
            : $user->can('view', $member);
    }

    public function delete(User $user, Document $document): bool
    {
        if (! $this->view($user, $document)) {
            return false;
        }

        $member = $document->member()->withoutGlobalScope(LocationScope::class)->first();

        return $user->can('update', $member)
            && ($document->uploaded_by === $user->id || $user->can(Permission::MembersDelete->value));
    }
}
