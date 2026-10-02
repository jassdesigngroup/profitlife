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
 * Congela desde hoy. Si se indica, `resumesOn` es el día en que se reanuda
 * sola. El total de días congelados no puede superar el máximo del plan.
 */
class FreezeMembership
{
    public function __construct(
        private readonly MembershipStatusChanger $statuses,
        private readonly AuditLogger $audit,
    ) {}

    public function execute(Membership $membership, ?CarbonImmutable $resumesOn, ?string $reason, User $actor): void
    {
        $today = BusinessDate::today();

        if ($membership->status !== MembershipStatus::Active) {
            throw ValidationException::withMessages(['freeze' => 'Solo se puede congelar una membresía activa.']);
        }

        if (! $membership->plan->allowsFreeze()) {
            throw ValidationException::withMessages(['freeze' => 'El plan de esta membresía no permite congelaciones.']);
        }

        $left = $membership->freezeDaysLeft();

        if ($left <= 0) {
            throw ValidationException::withMessages(['freeze' => 'Ya usó todos los días de congelación del plan.']);
        }

        if ($resumesOn !== null) {
            if ($resumesOn->lessThanOrEqualTo($today)) {
                throw ValidationException::withMessages(['resumesOn' => 'La fecha de reanudación debe ser posterior a hoy.']);
            }

            if ($today->diffInDays($resumesOn) > $left) {
                throw ValidationException::withMessages(['resumesOn' => "Solo le quedan {$left} días de congelación."]);
            }
        }

        DB::transaction(function () use ($membership, $resumesOn, $reason, $actor, $today) {
            $membership->freezes()->create([
                'starts_on' => $today->toDateString(),
                'ends_on' => $resumesOn?->toDateString(),
                'reason' => $reason,
                'created_by' => $actor->id,
            ]);

            $this->statuses->change($membership, MembershipStatus::Frozen, $reason ?: 'Congelación', $actor);

            $this->audit->log('memberships', AuditEvent::MembershipFrozen, $membership, $actor, [
                'resumes_on' => $resumesOn?->toDateString(),
            ]);
        });
    }
}
