<?php

namespace App\Domain\Physiotherapy\Policies;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Physiotherapy\Enums\NoteType;
use App\Domain\Physiotherapy\Models\ClinicalNote;
use App\Domain\Physiotherapy\Services\ClinicalAccess;

/**
 * Una nota sin firmar solo la edita y firma su autor (del equipo). Una nota
 * firmada no cambia: se le agregan adendas.
 */
class ClinicalNotePolicy
{
    public function __construct(private readonly ClinicalAccess $access) {}

    public function update(User $user, ClinicalNote $note): bool
    {
        return $user->can(Permission::ClinicalNotesUpdate->value)
            && ! $note->isSigned()
            && $user->staff?->id === $note->author_id
            && $this->access->isOnTeam($user, $note->record);
    }

    public function sign(User $user, ClinicalNote $note): bool
    {
        return $user->can(Permission::ClinicalNotesSign->value)
            && ! $note->isSigned()
            && $user->staff?->id === $note->author_id
            && $this->access->isOnTeam($user, $note->record);
    }

    public function addAddendum(User $user, ClinicalNote $note): bool
    {
        return $user->can(Permission::ClinicalNotesSign->value)
            && $note->isSigned()
            && $note->type !== NoteType::Addendum
            && $this->access->isOnTeam($user, $note->record);
    }
}
