<?php

namespace App\Domain\Memberships\Actions;

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Billing\Actions\IssueInvoice;
use App\Domain\Billing\DTOs\InvoiceLine;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\Membership;
use App\Domain\Memberships\Services\MembershipStatusChanger;
use App\Domain\Settings\Services\Settings;
use App\Support\BusinessDate;
use Illuminate\Support\Facades\DB;

/**
 * Renovación automática: crea el periodo siguiente con la tarifa vigente del
 * plan y su comprobante pendiente. Vence a los días de gracia de la sede.
 * Devuelve null si el plan ya no se puede renovar.
 */
class RenewMembership
{
    public function __construct(
        private readonly IssueInvoice $issueInvoice,
        private readonly MembershipStatusChanger $statuses,
        private readonly Settings $settings,
        private readonly AuditLogger $audit,
    ) {}

    public function execute(Membership $previous): ?Membership
    {
        $plan = $previous->plan;

        if ($plan === null || $plan->trashed() || ! $plan->is_active || $plan->duration_unit === null || ! $plan->isValidAt($previous->purchase_location_id)) {
            return null;
        }

        return DB::transaction(function () use ($previous, $plan) {
            $startsOn = $previous->ends_on->addDay();
            $endsOn = $plan->duration_unit->endOfPeriod($startsOn, $plan->duration_count);
            $member = $previous->member()->withoutGlobalScopes()->firstOrFail();

            $membership = $member->memberships()->create([
                'membership_plan_id' => $plan->id,
                'purchase_location_id' => $previous->purchase_location_id,
                'status' => $startsOn->greaterThan(BusinessDate::today()) ? MembershipStatus::Pending : MembershipStatus::Active,
                'starts_on' => $startsOn->toDateString(),
                'ends_on' => $endsOn->toDateString(),
                'price_cents' => $plan->price_cents,
                'currency' => $plan->currency,
                'auto_renews' => true,
                'renewed_from_id' => $previous->id,
            ]);
            $this->statuses->record($membership, 'Renovación automática', null);

            $this->issueInvoice->execute(
                $member,
                $previous->purchaseLocation,
                [new InvoiceLine(
                    description: "Renovación {$plan->name} ({$startsOn->format('d/m/Y')} – {$endsOn->format('d/m/Y')})",
                    unitPriceCents: $plan->price_cents,
                    taxRateBps: $plan->tax_rate_bps,
                    billableType: 'membership',
                    billableId: $membership->id,
                )],
                $plan->currency,
                $startsOn->addDays($this->settings->graceDays($previous->purchase_location_id)),
                null,
            );

            $this->audit->log('memberships', AuditEvent::MembershipRenewed, $membership, null, [
                'member_id' => $member->id,
                'renewed_from_id' => $previous->id,
                'price_cents' => $plan->price_cents,
            ]);

            return $membership;
        });
    }
}
