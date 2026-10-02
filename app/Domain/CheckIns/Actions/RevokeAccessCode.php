<?php

namespace App\Domain\CheckIns\Actions;

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\CheckIns\Models\MemberAccessCredential;
use App\Domain\Identity\Models\User;

class RevokeAccessCode
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function execute(MemberAccessCredential $credential, User $actor): void
    {
        $credential->forceFill(['is_active' => false])->save();

        $this->audit->log('check_ins', AuditEvent::AccessCodeRevoked, $credential->member()->withoutGlobalScopes()->first(), $actor, [
            'credential_id' => $credential->id,
        ]);
    }
}
