<?php

namespace App\Domain\Members\Actions;

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Models\User;
use App\Domain\Members\Models\MemberNote;

class DeleteMemberNote
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function execute(MemberNote $note, User $actor): void
    {
        $note->delete();

        $this->audit->log('members', AuditEvent::NoteDeleted, $note->member, $actor, ['note_id' => $note->id]);
    }
}
