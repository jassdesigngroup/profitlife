<?php

namespace App\Domain\Members\Actions;

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Models\User;
use App\Domain\Members\Models\Member;
use App\Domain\Members\Models\MemberNote;

class AddMemberNote
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function execute(Member $member, string $body, bool $pinned, User $author): MemberNote
    {
        $note = $member->notes()->create([
            'author_id' => $author->id,
            'body' => $body,
            'is_pinned' => $pinned,
        ]);

        // Sin el texto de la nota en la auditoría.
        $this->audit->log('members', AuditEvent::NoteAdded, $member, $author, ['note_id' => $note->id]);

        return $note;
    }
}
