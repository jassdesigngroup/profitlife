<?php

namespace App\Domain\Members\Actions;

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Models\User;
use App\Domain\Members\Models\Member;

class ClearCheckinPin
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function execute(Member $member, User $actor): void
    {
        if (! $member->hasCheckinPin()) {
            return;
        }

        $member->forceFill(['checkin_pin_hash' => null])->saveQuietly();

        $this->audit->log('members', AuditEvent::PinCleared, $member, $actor);
    }
}
