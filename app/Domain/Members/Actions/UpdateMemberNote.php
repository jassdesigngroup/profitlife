<?php

namespace App\Domain\Members\Actions;

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Models\User;
use App\Domain\Members\Models\MemberNote;

class UpdateMemberNote
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function execute(MemberNote $note, string $body, User $actor): MemberNote
    {
        $note->update(['body' => $body]);

        $this->audit->log('members', AuditEvent::NoteUpdated, $note->member, $actor, ['note_id' => $note->id]);

        return $note;
    }
}
