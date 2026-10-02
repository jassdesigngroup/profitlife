<?php

namespace App\Domain\CheckIns\Actions;

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\CheckIns\Models\MemberAccessCredential;
use App\Domain\CheckIns\Notifications\MemberAccessCodeNotification;
use App\Domain\Identity\Models\User;
use App\Domain\Members\Models\Member;
use Illuminate\Validation\ValidationException;

class SendAccessCode
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function execute(Member $member, MemberAccessCredential $credential, User $actor): void
    {
        if (blank($member->email)) {
            throw ValidationException::withMessages(['access' => 'El cliente no tiene correo registrado.']);
        }

        if ($credential->member_id !== $member->id || ! $credential->isUsable()) {
            throw ValidationException::withMessages(['access' => 'El código ya no es válido. Genere uno nuevo.']);
        }

        $member->notify(new MemberAccessCodeNotification($credential));

        $this->audit->log('check_ins', AuditEvent::AccessCodeSent, $member, $actor, ['credential_id' => $credential->id]);
    }
}
