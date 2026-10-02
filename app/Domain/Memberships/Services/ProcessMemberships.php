<?php

namespace App\Domain\Memberships\Services;

use App\Domain\Appointments\Services\SessionLedger;
use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Memberships\Actions\RenewMembership;
use App\Domain\Memberships\Actions\UnfreezeMembership;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\Membership;
use App\Domain\Memberships\Models\MembershipFreeze;
use App\Support\BusinessDate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tarea diaria de membresías. Idempotente: ejecutarla dos veces el mismo
 * día no cambia nada la segunda vez. Corre sin usuario (sin LocationScope).
 *
 * Orden: reanudar congelaciones → vencer o renovar → activar las que
 * empiezan → suspender las que no pagaron tras los días de gracia.
 */
class ProcessMemberships
{
    public function __construct(
        private readonly MembershipStatusChanger $statuses,
        private readonly UnfreezeMembership $unfreeze,
        private readonly RenewMembership $renew,
        private readonly SessionLedger $ledger,
    ) {}

    /**
     * @return array{resumed: int, renewed: int, expired: int, activated: int, suspended: int}
     */
    public function run(?CarbonImmutable $today = null): array
    {
        $today ??= BusinessDate::today();
        $summary = ['resumed' => 0, 'renewed' => 0, 'expired' => 0, 'activated' => 0, 'suspended' => 0];

        // 1. Congelaciones que terminan hoy o que agotaron el máximo del plan.
        $this->query()->where('status', MembershipStatus::Frozen)->with(['plan', 'freezes'])->get()
            ->each(function (Membership $membership) use ($today, &$summary) {
                $open = $membership->freezes->sortByDesc('starts_on')->first();

                if ($open === null) {
                    return;
                }

                $resumeOn = null;
                if ($open->ends_on !== null && $open->ends_on->lessThanOrEqualTo($today)) {
                    $resumeOn = $open->ends_on;
                } else {
                    $usedBefore = $membership->freezes->where('id', '!=', $open->id)->sum(fn (MembershipFreeze $f) => $f->days($today));
                    $allowed = max(0, (int) $membership->plan->max_freeze_days - $usedBefore);
                    $limit = $open->starts_on->addDays($allowed);
                    if ($limit->lessThanOrEqualTo($today)) {
                        $resumeOn = $limit;
                    }
                }

                if ($resumeOn !== null) {
                    $this->unfreeze->execute($membership, null, $resumeOn);
                    $summary['resumed']++;
                }
            });

        // 2. Vencidas: se renuevan (si corresponde) y se marcan como vencidas.
        $this->query()->whereIn('status', [MembershipStatus::Active, MembershipStatus::Suspended])
            ->whereDate('ends_on', '<', $today)
            ->with('plan.locations')
            ->get()
            ->each(function (Membership $membership) use (&$summary) {
                $alreadyRenewed = Membership::query()->withoutGlobalScopes()->where('renewed_from_id', $membership->id)->exists();

                if ($membership->auto_renews && ! $alreadyRenewed && $this->renew->execute($membership) !== null) {
                    $summary['renewed']++;
                }

                $this->statuses->change($membership, MembershipStatus::Expired, 'Fin de la vigencia', null);
                $this->ledger->expireMembership($membership);
                $summary['expired']++;
            });

        // 3. Las que empiezan hoy.
        $this->query()->where('status', MembershipStatus::Pending)
            ->whereDate('starts_on', '<=', $today)
            ->get()
            ->each(function (Membership $membership) use (&$summary) {
                $this->statuses->change($membership, MembershipStatus::Active, 'Inicio de la vigencia', null);
                $summary['activated']++;
            });

        // 4. Sin pago tras los días de gracia (vencimiento del comprobante).
        $overdue = Invoice::query()->withoutGlobalScopes()
            ->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid])
            ->whereDate('due_on', '<', $today)
            ->with('items')
            ->get()
            ->flatMap(fn (Invoice $i) => $i->items->where('billable_type', 'membership')->pluck('billable_id'));

        $this->query()->where('status', MembershipStatus::Active)->whereIn('id', $overdue->all())->get()
            ->each(function (Membership $membership) use (&$summary) {
                $this->statuses->change($membership, MembershipStatus::Suspended, 'Pago pendiente', null);
                $summary['suspended']++;
            });

        return $summary;
    }

    /**
     * @return Builder<Membership>
     */
    private function query(): Builder
    {
        return Membership::query()->withoutGlobalScopes()->whereNull('deleted_at');
    }
}
