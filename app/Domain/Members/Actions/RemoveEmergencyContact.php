<?php

namespace App\Domain\Members\Actions;

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Models\User;
use App\Domain\Members\Models\EmergencyContact;

class RemoveEmergencyContact
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function execute(EmergencyContact $contact, User $actor): void
    {
        $member = $contact->member;
        $wasPrimary = $contact->is_primary;
        $contact->delete();

        if ($wasPrimary) {
            $member->emergencyContacts()->first()?->update(['is_primary' => true]);
        }

        $this->audit->log('members', AuditEvent::ContactRemoved, $member, $actor);
    }
}
