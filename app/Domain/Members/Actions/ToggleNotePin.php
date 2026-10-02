<?php

namespace App\Domain\Members\Actions;

use App\Domain\Members\Models\MemberNote;

class ToggleNotePin
{
    public function execute(MemberNote $note): MemberNote
    {
        $note->update(['is_pinned' => ! $note->is_pinned]);

        return $note;
    }
}
