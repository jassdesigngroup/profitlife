<?php

namespace App\Domain\CheckIns\Actions;

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\CheckIns\Enums\CredentialType;
use App\Domain\CheckIns\Models\MemberAccessCredential;
use App\Domain\Identity\Models\User;
use App\Domain\Members\Models\Member;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Genera el código QR de acceso del cliente. Anula el anterior: un código
 * perdido deja de servir en cuanto se genera uno nuevo.
 */
class IssueAccessCode
{
    /** Prefijo que reconoce el kiosco; el resto es aleatorio. */
    public const PREFIX = 'AC1.';

    public function __construct(private readonly AuditLogger $audit) {}

    public function execute(Member $member, ?User $actor): MemberAccessCredential
    {
        return DB::transaction(function () use ($member, $actor) {
            $replaced = MemberAccessCredential::query()->withoutGlobalScopes()
                ->where('member_id', $member->id)
                ->where('type', CredentialType::Qr)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $token = self::PREFIX.Str::random(40);

            $credential = MemberAccessCredential::query()->create([
                'member_id' => $member->id,
                'type' => CredentialType::Qr,
                'token_hash' => MemberAccessCredential::hashToken($token),
                'token_encrypted' => $token,
                'is_active' => true,
            ]);

            $this->audit->log('check_ins', AuditEvent::AccessCodeIssued, $member, $actor, [
                'credential_id' => $credential->id,
                'replaced' => $replaced,
            ]);

            return $credential;
        });
    }
}
