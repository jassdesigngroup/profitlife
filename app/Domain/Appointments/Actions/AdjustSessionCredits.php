<?php

namespace App\Domain\Appointments\Actions;

use App\Domain\Appointments\Enums\CreditReason;
use App\Domain\Appointments\Models\Service;
use App\Domain\Appointments\Models\SessionCredit;
use App\Domain\Appointments\Services\SessionLedger;
use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Models\User;
use App\Domain\Memberships\Models\Membership;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ajuste manual del saldo de sesiones de una membresía (+ cortesía,
 * − corrección). No deja el saldo en negativo.
 */
class AdjustSessionCredits
{
    public function __construct(
        private readonly SessionLedger $ledger,
        private readonly AuditLogger $audit,
    ) {}

    public function execute(Membership $membership, Service $service, int $delta, string $reason, User $actor): void
    {
        $reason = trim($reason);

        if ($delta === 0 || abs($delta) > 100) {
            throw ValidationException::withMessages(['adjustDelta' => 'Indique una cantidad entre -100 y 100, distinta de 0.']);
        }

        if ($reason === '') {
            throw ValidationException::withMessages(['adjustReason' => 'Indique el motivo del ajuste.']);
        }

        if (! $membership->status->isCurrent()) {
            throw ValidationException::withMessages(['adjustDelta' => 'La membresía no está vigente.']);
        }

        DB::transaction(function () use ($membership, $service, $delta, $reason, $actor) {
            if ($this->ledger->balanceFor($membership, $service) + $delta < 0) {
                throw ValidationException::withMessages(['adjustDelta' => 'El saldo no puede quedar en negativo.']);
            }

            SessionCredit::query()->create([
                'member_id' => $membership->member_id,
                'membership_id' => $membership->id,
                'service_id' => $service->id,
                'delta' => $delta,
                'reason' => CreditReason::Adjust,
                'expires_on' => $membership->ends_on?->toDateString(),
                'created_by' => $actor->id,
            ]);

            $this->audit->log('appointments', AuditEvent::CreditsAdjusted, $membership, $actor, [
                'member_id' => $membership->member_id,
                'service_id' => $service->id,
                'delta' => $delta,
                'reason' => $reason,
            ]);
        });
    }
}
