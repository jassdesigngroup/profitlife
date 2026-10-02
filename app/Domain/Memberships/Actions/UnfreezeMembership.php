<?php

namespace App\Domain\Memberships\Actions;

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Models\User;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\Membership;
use App\Domain\Memberships\Services\MembershipStatusChanger;
use App\Support\BusinessDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reanuda una membresía congelada y corre su vencimiento por los días congelados.
 */
class UnfreezeMembership
{
    public function __construct(
        private readonly MembershipStatusChanger $statuses,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  CarbonImmutable|null  $resumeOn  día de reanudación; por defecto hoy
     */
    public function execute(Membership $membership, ?User $actor, ?CarbonImmutable $resumeOn = null): void
    {
        if ($membership->status !== MembershipStatus::Frozen) {
            throw ValidationException::withMessages(['freeze' => 'La membresía no está congelada.']);
        }

        $resumeOn ??= BusinessDate::today();
        $freeze = $membership->freezes()->whereNull('ends_on')->first()
            ?? $membership->freezes()->whereDate('ends_on', '>=', $resumeOn)->first()
            ?? $membership->freezes()->first();

        DB::transaction(function () use ($membership, $freeze, $resumeOn, $actor) {
            $days = 0;

            if ($freeze !== null) {
                if ($freeze->ends_on === null || $freeze->ends_on->greaterThan($resumeOn)) {
                    $freeze->update(['ends_on' => $resumeOn->toDateString()]);
                }
                $days = $freeze->days($resumeOn);
            }

            if ($days > 0 && $membership->ends_on !== null) {
                $membership->forceFill(['ends_on' => $membership->ends_on->addDays($days)->toDateString()])->save();
            }

            $this->statuses->change($membership, MembershipStatus::Active, "Reanudada ({$days} días congelados)", $actor);

            $this->audit->log('memberships', AuditEvent::MembershipUnfrozen, $membership, $actor, [
                'frozen_days' => $days,
                'new_ends_on' => $membership->ends_on?->toDateString(),
            ]);
        });
    }
}
