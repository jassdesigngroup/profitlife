<?php

namespace App\Domain\Members\Actions;

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Models\User;
use App\Domain\Members\Models\Member;
use App\Domain\Members\Services\CheckinPinRules;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Guarda el PIN de check-in como hash. El valor nunca se registra ni se muestra.
 */
class SetCheckinPin
{
    public function __construct(
        private readonly CheckinPinRules $rules,
        private readonly AuditLogger $audit,
    ) {}

    public function execute(Member $member, #[\SensitiveParameter] string $pin, User $actor): void
    {
        if (($problem = $this->rules->problem($pin, $member)) !== null) {
            throw ValidationException::withMessages(['pin' => $problem]);
        }

        $hadPin = $member->hasCheckinPin();

        $member->forceFill(['checkin_pin_hash' => Hash::make($pin)])->saveQuietly();

        $this->audit->log('members', AuditEvent::PinSet, $member, $actor, ['replaced' => $hadPin]);
    }
}
