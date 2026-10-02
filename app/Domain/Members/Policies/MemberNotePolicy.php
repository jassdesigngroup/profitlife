<?php

namespace App\Domain\Members\Policies;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Members\Models\MemberNote;

/**
 * Cada quien edita sus notas; borrar las de otros exige `members.delete`.
 */
class MemberNotePolicy
{
    public function update(User $user, MemberNote $note): bool
    {
        return $note->author_id === $user->id && $user->can('update', $note->member);
    }

    public function pin(User $user, MemberNote $note): bool
    {
        return $user->can('update', $note->member);
    }

    public function delete(User $user, MemberNote $note): bool
    {
        if (! $user->can('update', $note->member)) {
            return false;
        }

        return $note->author_id === $user->id || $user->can(Permission::MembersDelete->value);
    }
}
