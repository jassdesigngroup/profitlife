<?php

namespace App\Domain\CheckIns\Services;

use App\Domain\CheckIns\Models\CheckIn;
use App\Domain\Memberships\Enums\VisitLimitPeriod;
use App\Domain\Memberships\Models\Membership;
use App\Domain\Settings\Services\Settings;
use Carbon\CarbonImmutable;

/**
 * Uso de los planes con límite de ingresos. Se cuenta como máximo un
 * ingreso por día local, y el periodo (semana o mes) se ancla al inicio de
 * la membresía.
 */
class VisitUsage
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * @return array{limit: ?int, used: int, today: bool, from: CarbonImmutable, to: CarbonImmutable}
     */
    public function for(Membership $membership, CarbonImmutable $today): array
    {
        $plan = $membership->plan;
        [$from, $to] = $this->period($membership, $plan->visit_limit_period ?? VisitLimitPeriod::Term, $today);

        if ($plan->visit_limit_count === null) {
            return ['limit' => null, 'used' => 0, 'today' => false, 'from' => $from, 'to' => $to];
        }

        $tz = $this->settings->displayTimezone();

        $days = CheckIn::query()->withoutGlobalScopes()
            ->where('membership_id', $membership->id)
            ->accepted()
            ->whereBetween('checked_in_at', [
                CarbonImmutable::parse($from->toDateString(), $tz)->utc(),
                CarbonImmutable::parse($to->toDateString(), $tz)->endOfDay()->utc(),
            ])
            ->pluck('checked_in_at')
            ->map(fn ($at) => $at->copy()->setTimezone($tz)->format('Y-m-d'))
            ->unique();

        return [
            'limit' => (int) $plan->visit_limit_count,
            'used' => $days->count(),
            'today' => $days->contains($today->toDateString()),
            'from' => $from,
            'to' => $to,
        ];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function period(Membership $membership, VisitLimitPeriod $period, CarbonImmutable $today): array
    {
        $start = CarbonImmutable::createFromFormat('!Y-m-d', $membership->starts_on->toDateString(), 'UTC');
        $end = $membership->ends_on
            ? CarbonImmutable::createFromFormat('!Y-m-d', $membership->ends_on->toDateString(), 'UTC')
            : $today;

        if ($today->lessThan($start)) {
            return [$start, $start];
        }

        return match ($period) {
            VisitLimitPeriod::Week => (function () use ($start, $today) {
                $from = $start->addDays(intdiv((int) $start->diffInDays($today), 7) * 7);

                return [$from, $from->addDays(6)];
            })(),
            VisitLimitPeriod::Month => (function () use ($start, $today) {
                $n = max(0, (int) floor($start->diffInMonths($today)) - 1);
                while ($start->addMonthsNoOverflow($n + 1)->lessThanOrEqualTo($today)) {
                    $n++;
                }

                return [$start->addMonthsNoOverflow($n), $start->addMonthsNoOverflow($n + 1)->subDay()];
            })(),
            VisitLimitPeriod::Term => [$start, $end],
        };
    }
}
