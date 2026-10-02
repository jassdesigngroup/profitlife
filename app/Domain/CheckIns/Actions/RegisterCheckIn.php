<?php

namespace App\Domain\CheckIns\Actions;

use App\Domain\Billing\Models\Invoice;
use App\Domain\CheckIns\DTOs\CheckInOutcome;
use App\Domain\CheckIns\Enums\CheckInMethod;
use App\Domain\CheckIns\Enums\CheckInResult;
use App\Domain\CheckIns\Enums\RejectionReason;
use App\Domain\CheckIns\Models\CheckIn;
use App\Domain\CheckIns\Models\KioskDevice;
use App\Domain\CheckIns\Services\MembershipAccess;
use App\Domain\CheckIns\Services\VisitUsage;
use App\Domain\Identity\Models\User;
use App\Domain\Locations\Models\Location;
use App\Domain\Locations\Services\OpeningHours;
use App\Domain\Members\Enums\MemberStatus;
use App\Domain\Members\Models\Member;
use App\Domain\Memberships\Models\Membership;
use App\Domain\Settings\Services\Settings;
use App\Support\BusinessDate;
use App\Support\Money;
use App\Support\Scopes\LocationScope;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Registra un intento de ingreso (kiosco o recepción) y decide si se
 * acepta. Siempre deja constancia, también de los rechazos.
 *
 * La autorización del staff la hace quien llama (Policy CheckIn@create);
 * el kiosco llega autenticado con el token de su dispositivo.
 */
class RegisterCheckIn
{
    public function __construct(
        private readonly MembershipAccess $access,
        private readonly VisitUsage $visits,
        private readonly OpeningHours $hours,
        private readonly Settings $settings,
    ) {}

    public function execute(
        Location $location,
        CheckInMethod $method,
        ?Member $member,
        ?KioskDevice $device = null,
        ?User $actor = null,
    ): CheckInOutcome {
        if ($member === null) {
            return new CheckInOutcome($this->record($location, $method, null, null, $device, $actor, RejectionReason::NotFound), null, null);
        }

        return DB::transaction(function () use ($location, $method, $member, $device, $actor) {
            // Bloquea al cliente para que dos lecturas simultáneas no cuenten doble.
            $member = Member::query()->withoutGlobalScope(LocationScope::class)->lockForUpdate()->findOrFail($member->id);
            $now = Date::now();
            $today = BusinessDate::today();

            if ($member->status !== MemberStatus::Active) {
                return $this->reject($location, $method, $member, null, $device, $actor, RejectionReason::MemberInactive);
            }

            $recent = $this->recentAccepted($member, $now->toImmutable());
            if ($recent !== null) {
                return $this->reject($location, $method, $member, $recent->membership, $device, $actor, RejectionReason::Duplicate);
            }

            [$membership, $reason] = $this->access->resolve($member, $location->id, $today);
            if ($reason !== null) {
                return $this->reject($location, $method, $member, $membership, $device, $actor, $reason);
            }

            $usage = $this->visits->for($membership, $today);
            if ($usage['limit'] !== null && ! $usage['today'] && $usage['used'] >= $usage['limit']) {
                return $this->reject($location, $method, $member, $membership, $device, $actor, RejectionReason::VisitLimitReached);
            }

            $checkIn = $this->record($location, $method, $member, $membership, $device, $actor, null);
            $visitsLeft = $usage['limit'] === null ? null : max(0, $usage['limit'] - $usage['used'] - ($usage['today'] ? 0 : 1));

            return new CheckInOutcome(
                $checkIn,
                $member,
                $membership,
                $this->warnings($location, $member, $membership, $visitsLeft),
                $visitsLeft,
            );
        });
    }

    /**
     * Ingreso aceptado del mismo cliente dentro de la ventana de repetidos.
     */
    public function recentAccepted(Member $member, \DateTimeInterface $now): ?CheckIn
    {
        $minutes = $this->settings->checkInDuplicateMinutes();

        if ($minutes === 0) {
            return null;
        }

        return CheckIn::query()->withoutGlobalScopes()
            ->where('member_id', $member->id)
            ->accepted()
            ->where('checked_in_at', '>=', CarbonImmutable::instance($now)->subMinutes($minutes))
            ->with('membership.plan')
            ->latest('checked_in_at')
            ->first();
    }

    /**
     * @return list<string>
     */
    private function warnings(Location $location, Member $member, Membership $membership, ?int $visitsLeft): array
    {
        $warnings = [];

        $open = Invoice::query()->withoutGlobalScopes()->where('member_id', $member->id)->open()->get();
        $balance = (int) $open->sum(fn (Invoice $i) => $i->balanceCents());
        if ($balance > 0) {
            $due = $open->whereNotNull('due_on')->sortBy('due_on')->first()?->due_on;
            $warnings[] = 'Tiene un saldo pendiente de '.Money::ofCents($balance, $open->first()->currency)->format()
                .($due ? ' (vence el '.$due->format('d/m/Y').')' : '').'.';
        }

        if (! $this->hours->isOpenAt($location, Date::now())) {
            $warnings[] = 'Ingreso fuera del horario de atención de la sede.';
        }

        if ($visitsLeft !== null) {
            $warnings[] = $visitsLeft === 0
                ? 'Este es su último ingreso disponible del periodo.'
                : "Le quedan {$visitsLeft} ingresos en el periodo.";
        }

        $days = $membership->daysRemaining();
        if ($days !== null && $days <= 5) {
            $warnings[] = 'La membresía vence el '.$membership->ends_on->format('d/m/Y').'.';
        }

        return $warnings;
    }

    private function reject(
        Location $location,
        CheckInMethod $method,
        Member $member,
        ?Membership $membership,
        ?KioskDevice $device,
        ?User $actor,
        RejectionReason $reason,
    ): CheckInOutcome {
        return new CheckInOutcome($this->record($location, $method, $member, $membership, $device, $actor, $reason), $member, $membership);
    }

    private function record(
        Location $location,
        CheckInMethod $method,
        ?Member $member,
        ?Membership $membership,
        ?KioskDevice $device,
        ?User $actor,
        ?RejectionReason $reason,
    ): CheckIn {
        return CheckIn::query()->create([
            'member_id' => $member?->id,
            'location_id' => $location->id,
            'membership_id' => $membership?->id,
            'kiosk_device_id' => $device?->id,
            'registered_by' => $actor?->id,
            'method' => $method,
            'result' => $reason === null ? CheckInResult::Accepted : CheckInResult::Rejected,
            'rejection_reason' => $reason,
            'checked_in_at' => Date::now(),
        ]);
    }
}
