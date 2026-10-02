<?php

namespace App\Domain\Memberships\Actions;

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Billing\Actions\IssueInvoice;
use App\Domain\Billing\Actions\RecordPayment;
use App\Domain\Billing\DTOs\InvoiceLine;
use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Locations\Models\Location;
use App\Domain\Members\Models\Member;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\Membership;
use App\Domain\Memberships\Models\MembershipPlan;
use App\Domain\Memberships\Services\MembershipStatusChanger;
use App\Domain\Settings\Services\Settings;
use App\Support\BusinessDate;
use App\Support\Scopes\LocationScope;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Vende una membresía: crea el periodo, emite el comprobante (con matrícula
 * si es la primera vez y el plan la cobra) y, si se indica, registra el pago.
 */
class SellMembership
{
    public function __construct(
        private readonly IssueInvoice $issueInvoice,
        private readonly RecordPayment $recordPayment,
        private readonly MembershipStatusChanger $statuses,
        private readonly Settings $settings,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{amount_cents: int, method: PaymentMethod, reference: ?string}|null  $payment
     */
    public function execute(
        Member $member,
        MembershipPlan $plan,
        Location $purchaseLocation,
        CarbonImmutable $startsOn,
        User $actor,
        int $discountCents = 0,
        ?string $discountReason = null,
        ?array $payment = null,
    ): Membership {
        // Valores por defecto de la base (p. ej. auto_renews, tax_rate_bps) aunque el plan venga recién creado.
        $plan->refresh();

        $this->validate($member, $plan, $purchaseLocation, $actor, $discountCents, $discountReason);

        $endsOn = $plan->duration_unit->endOfPeriod($startsOn, $plan->duration_count);

        if ($endsOn->lessThan(BusinessDate::today())) {
            throw ValidationException::withMessages(['startsOn' => 'Con esa fecha de inicio la membresía ya estaría vencida.']);
        }

        $this->assertNoOverlap($member, $startsOn, $endsOn);

        $membership = DB::transaction(function () use ($member, $plan, $purchaseLocation, $startsOn, $endsOn, $actor, $discountCents, $discountReason) {
            $isFirst = ! $member->memberships()->withoutGlobalScope(LocationScope::class)->withTrashed()->exists();

            $membership = $member->memberships()->create([
                'membership_plan_id' => $plan->id,
                'purchase_location_id' => $purchaseLocation->id,
                'status' => $startsOn->greaterThan(BusinessDate::today()) ? MembershipStatus::Pending : MembershipStatus::Active,
                'starts_on' => $startsOn->toDateString(),
                'ends_on' => $endsOn->toDateString(),
                'price_cents' => $plan->price_cents,
                'currency' => $plan->currency,
                'auto_renews' => $plan->auto_renews,
                'created_by' => $actor->id,
            ]);
            $this->statuses->record($membership, 'Venta', $actor);

            $lines = [new InvoiceLine(
                description: "Membresía {$plan->name} ({$startsOn->format('d/m/Y')} – {$endsOn->format('d/m/Y')})",
                unitPriceCents: $plan->price_cents,
                discountCents: $discountCents,
                taxRateBps: $plan->tax_rate_bps,
                billableType: 'membership',
                billableId: $membership->id,
            )];

            if ($isFirst && $plan->enrollment_fee_cents > 0) {
                $lines[] = new InvoiceLine('Matrícula', $plan->enrollment_fee_cents, taxRateBps: $plan->tax_rate_bps, billableType: 'membership_plan', billableId: $plan->id);
            }

            $this->issueInvoice->execute(
                $member,
                $purchaseLocation,
                $lines,
                $plan->currency,
                BusinessDate::today()->addDays($this->settings->graceDays($purchaseLocation->id)),
                $actor,
                $discountCents > 0 ? "Descuento: {$discountReason}" : null,
            );

            $this->audit->log('memberships', AuditEvent::MembershipSold, $membership, $actor, [
                'member_id' => $member->id,
                'plan' => $plan->name,
                'starts_on' => $startsOn->toDateString(),
                'ends_on' => $endsOn->toDateString(),
                'price_cents' => $plan->price_cents,
            ]);

            if ($discountCents > 0) {
                $this->audit->log('memberships', AuditEvent::DiscountApplied, $membership, $actor, [
                    'discount_cents' => $discountCents,
                    'reason' => $discountReason,
                ]);
            }

            return $membership;
        });

        if ($payment !== null && $payment['amount_cents'] > 0) {
            $invoice = $membership->invoice();
            $this->recordPayment->execute($invoice, $payment['amount_cents'], $payment['method'], $payment['reference'], $actor);
        }

        return $membership->refresh();
    }

    private function validate(Member $member, MembershipPlan $plan, Location $location, User $actor, int $discountCents, ?string $discountReason): void
    {
        if (! $actor->canAccessLocation($location->id)) {
            throw new AuthorizationException('No puede vender en una sede fuera de su alcance.');
        }

        if (! $plan->is_active || $plan->trashed() || $plan->duration_unit === null || ! $plan->isValidAt($location->id)) {
            throw ValidationException::withMessages(['planId' => 'El plan no está disponible en esta sede.']);
        }

        if ($discountCents < 0 || $discountCents > $plan->price_cents) {
            throw ValidationException::withMessages(['discount' => 'El descuento no puede superar el precio del plan.']);
        }

        if ($discountCents > 0) {
            if (! $actor->can(Permission::PaymentsDiscount->value)) {
                throw new AuthorizationException('No tiene permiso para aplicar descuentos.');
            }

            if (trim((string) $discountReason) === '') {
                throw ValidationException::withMessages(['discountReason' => 'Indique el motivo del descuento.']);
            }
        }
    }

    private function assertNoOverlap(Member $member, CarbonImmutable $startsOn, CarbonImmutable $endsOn): void
    {
        $overlap = $member->memberships()->withoutGlobalScope(LocationScope::class)
            ->whereIn('status', MembershipStatus::currentValues())
            ->whereDate('starts_on', '<=', $endsOn)
            ->whereDate('ends_on', '>=', $startsOn)
            ->orderByDesc('ends_on')
            ->first();

        if ($overlap !== null) {
            throw ValidationException::withMessages([
                'startsOn' => 'El cliente ya tiene una membresía vigente hasta el '.$overlap->ends_on->format('d/m/Y')
                    .'. La nueva puede iniciar desde el '.$overlap->ends_on->addDay()->format('d/m/Y').'.',
            ]);
        }
    }
}
