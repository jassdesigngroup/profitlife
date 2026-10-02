<?php

namespace App\Domain\Appointments\Services;

use App\Domain\Appointments\DTOs\Coverage;
use App\Domain\Appointments\Enums\CreditReason;
use App\Domain\Appointments\Models\Appointment;
use App\Domain\Appointments\Models\Service;
use App\Domain\Appointments\Models\SessionCredit;
use App\Domain\Identity\Models\User;
use App\Domain\Members\Models\Member;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\Membership;
use App\Support\Scopes\LocationScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Libro de sesiones incluidas en los planes. Cada membresía otorga sus
 * sesiones al venderse o renovarse; vencen con la membresía.
 */
class SessionLedger
{
    /**
     * Otorga las sesiones del plan a una membresía nueva.
     */
    public function grantForMembership(Membership $membership, ?User $actor = null): void
    {
        $membership->loadMissing('plan.planServices');

        foreach ($membership->plan->planServices as $included) {
            if ($included->isUnlimited() || $included->sessions_included <= 0) {
                continue;
            }

            SessionCredit::query()->create([
                'member_id' => $membership->member_id,
                'membership_id' => $membership->id,
                'service_id' => $included->service_id,
                'delta' => $included->sessions_included,
                'reason' => CreditReason::Grant,
                'expires_on' => $membership->ends_on?->toDateString(),
                'created_by' => $actor?->id,
            ]);
        }
    }

    /**
     * Da de baja el saldo que quede de una membresía que vence o se cancela.
     */
    public function expireMembership(Membership $membership, ?User $actor = null): void
    {
        $balances = SessionCredit::query()->withoutGlobalScopes()
            ->where('membership_id', $membership->id)
            ->selectRaw('service_id, SUM(delta) as balance')
            ->groupBy('service_id')
            ->get();

        foreach ($balances as $row) {
            if ((int) $row->balance > 0) {
                SessionCredit::query()->create([
                    'member_id' => $membership->member_id,
                    'membership_id' => $membership->id,
                    'service_id' => $row->service_id,
                    'delta' => -1 * (int) $row->balance,
                    'reason' => CreditReason::Expire,
                    'created_by' => $actor?->id,
                ]);
            }
        }
    }

    /**
     * Cómo se cubriría una cita del servicio en esa fecha local.
     */
    public function coverage(Member $member, Service $service, int $locationId, CarbonImmutable $localDate): Coverage
    {
        $memberships = $this->usableMemberships($member, $localDate);

        foreach ($memberships as $membership) {
            $included = $membership->plan->planServices->firstWhere('service_id', $service->id);
            if ($included?->isUnlimited()) {
                return new Coverage(Coverage::UNLIMITED, $membership, null, 0, $service->currency);
            }
        }

        foreach ($memberships as $membership) {
            $balance = $this->balanceFor($membership, $service);
            if ($balance > 0) {
                return new Coverage(Coverage::CREDIT, $membership, $balance - 1, 0, $service->currency);
            }
        }

        return new Coverage(Coverage::INVOICE, null, null, $service->priceCentsAt($locationId), $service->currency);
    }

    public function consume(Appointment $appointment, Membership $membership, ?User $actor): void
    {
        SessionCredit::query()->create([
            'member_id' => $appointment->member_id,
            'membership_id' => $membership->id,
            'service_id' => $appointment->service_id,
            'delta' => -1,
            'reason' => CreditReason::Consume,
            'appointment_id' => $appointment->id,
            'created_by' => $actor?->id,
        ]);
    }

    /**
     * Devuelve las sesiones descontadas por una cita (si las hubo).
     */
    public function refund(Appointment $appointment, ?User $actor): int
    {
        $used = $appointment->creditsUsed();

        if ($used <= 0) {
            return 0;
        }

        $membershipId = $appointment->credits()->where('reason', CreditReason::Consume)->latest('id')->value('membership_id');

        SessionCredit::query()->create([
            'member_id' => $appointment->member_id,
            'membership_id' => $membershipId,
            'service_id' => $appointment->service_id,
            'delta' => $used,
            'reason' => CreditReason::Refund,
            'appointment_id' => $appointment->id,
            'created_by' => $actor?->id,
        ]);

        return $used;
    }

    /**
     * Membresía de la que se descontó la cita, si se pagó con sesiones.
     */
    public function consumedFrom(Appointment $appointment): ?Membership
    {
        $id = $appointment->credits()->where('reason', CreditReason::Consume)->latest('id')->value('membership_id');

        return $id === null ? null : Membership::query()->withoutGlobalScopes()->find($id);
    }

    public function balanceFor(Membership $membership, Service $service): int
    {
        return (int) SessionCredit::query()->withoutGlobalScopes()
            ->where('membership_id', $membership->id)
            ->where('service_id', $service->id)
            ->sum('delta');
    }

    /**
     * Saldo por servicio de las membresías vigentes del cliente.
     *
     * @return Collection<int, object{service_id: int, membership_id: int, balance: int}>
     */
    public function balances(Member $member): Collection
    {
        $ids = Membership::query()->withoutGlobalScope(LocationScope::class)
            ->where('member_id', $member->id)
            ->whereIn('status', MembershipStatus::currentValues())
            ->pluck('id');

        return SessionCredit::query()->withoutGlobalScopes()
            ->whereIn('membership_id', $ids)
            ->selectRaw('service_id, membership_id, SUM(delta) as balance')
            ->groupBy('service_id', 'membership_id')
            ->get()
            ->map(fn ($row) => (object) ['service_id' => (int) $row->service_id, 'membership_id' => (int) $row->membership_id, 'balance' => (int) $row->balance]);
    }

    /**
     * Membresías activas (o por iniciar) que cubren la fecha, la que vence
     * primero antes.
     *
     * @return Collection<int, Membership>
     */
    private function usableMemberships(Member $member, CarbonImmutable $localDate): Collection
    {
        $date = $localDate->toDateString();

        return Membership::query()->withoutGlobalScope(LocationScope::class)
            ->where('member_id', $member->id)
            ->whereIn('status', [MembershipStatus::Active, MembershipStatus::Pending])
            ->whereDate('starts_on', '<=', $date)
            ->where(fn (Builder $q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $date))
            ->with('plan.planServices')
            ->orderBy('ends_on')
            ->get();
    }
}
