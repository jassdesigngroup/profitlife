<?php

namespace App\Domain\CheckIns\Services;

use App\Domain\CheckIns\Enums\RejectionReason;
use App\Domain\Members\Models\Member;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\Membership;
use App\Support\Scopes\LocationScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Elige la membresía con la que un cliente entra hoy a una sede o, si no
 * hay ninguna, el motivo del rechazo.
 */
class MembershipAccess
{
    /**
     * @return array{0: ?Membership, 1: ?RejectionReason}
     */
    public function resolve(Member $member, int $locationId, CarbonImmutable $today): array
    {
        $candidates = Membership::query()->withoutGlobalScope(LocationScope::class)
            ->where('member_id', $member->id)
            ->whereIn('status', MembershipStatus::currentValues())
            ->whereDate('starts_on', '<=', $today->toDateString())
            ->where(fn (Builder $q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $today->toDateString()))
            // Los paquetes de sesiones sin acceso al gimnasio no sirven para entrar.
            ->whereHas('plan', fn (Builder $p) => $p->withTrashed()->where('includes_gym_access', true))
            ->with('plan.locations')
            ->orderBy('starts_on')
            ->get();

        // Una membresía por iniciar cuyo día ya llegó cuenta como activa aunque
        // el proceso diario todavía no la haya activado.
        $usable = $candidates->filter(fn (Membership $m) => in_array($m->status, [MembershipStatus::Active, MembershipStatus::Pending], true));

        if ($usable->isNotEmpty()) {
            $here = $usable->first(fn (Membership $m) => $m->plan->isValidAt($locationId));

            return $here !== null ? [$here, null] : [$usable->first(), RejectionReason::LocationNotAllowed];
        }

        if ($frozen = $candidates->firstWhere('status', MembershipStatus::Frozen)) {
            return [$frozen, RejectionReason::MembershipFrozen];
        }

        if ($suspended = $candidates->firstWhere('status', MembershipStatus::Suspended)) {
            return [$suspended, RejectionReason::MembershipSuspended];
        }

        return [null, RejectionReason::MembershipExpired];
    }
}
