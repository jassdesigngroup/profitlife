<?php

namespace App\Domain\Consents\Actions;

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Consents\Models\Consent;
use App\Domain\Identity\Models\User;
use Illuminate\Validation\ValidationException;

class RevokeConsent
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function execute(Consent $consent, User $actor): Consent
    {
        if ($consent->isRevoked()) {
            throw ValidationException::withMessages(['consent' => 'El consentimiento ya estaba revocado.']);
        }

        $consent->update(['revoked_at' => now()]);

        $this->audit->log('consents', AuditEvent::ConsentRevoked, $consent, $actor, [
            'member_id' => $consent->member_id,
            'type' => $consent->template->type->value,
            'version' => $consent->template->version,
        ]);

        return $consent;
    }
}
