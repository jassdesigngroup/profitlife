<?php

namespace App\Domain\Billing\Actions;

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Billing\DTOs\InvoiceLine;
use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Services\InvoiceNumber;
use App\Domain\Identity\Models\User;
use App\Domain\Locations\Models\Location;
use App\Domain\Members\Models\Member;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Emite un comprobante numerado con sus renglones y totales.
 */
class IssueInvoice
{
    public function __construct(
        private readonly InvoiceNumber $numbers,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  list<InvoiceLine>  $lines
     */
    public function execute(
        Member $member,
        Location $location,
        array $lines,
        string $currency,
        CarbonImmutable $dueOn,
        ?User $actor,
        ?string $notes = null,
    ): Invoice {
        return DB::transaction(function () use ($member, $location, $lines, $currency, $dueOn, $actor, $notes) {
            $invoice = Invoice::query()->create([
                'number' => $this->numbers->next($location),
                'member_id' => $member->id,
                'location_id' => $location->id,
                'status' => InvoiceStatus::Issued,
                'issued_at' => now(),
                'due_on' => $dueOn->toDateString(),
                'subtotal_cents' => array_sum(array_map(fn (InvoiceLine $l) => $l->grossCents(), $lines)),
                'discount_cents' => array_sum(array_map(fn (InvoiceLine $l) => $l->discountCents, $lines)),
                'tax_cents' => array_sum(array_map(fn (InvoiceLine $l) => $l->taxCents(), $lines)),
                'total_cents' => array_sum(array_map(fn (InvoiceLine $l) => $l->totalCents(), $lines)),
                'paid_cents' => 0,
                'currency' => $currency,
                'notes' => $notes,
                'created_by' => $actor?->id,
            ]);

            foreach ($lines as $line) {
                $invoice->items()->create([
                    'billable_type' => $line->billableType,
                    'billable_id' => $line->billableId,
                    'description' => mb_substr($line->description, 0, 255),
                    'quantity' => $line->quantity,
                    'unit_price_cents' => $line->unitPriceCents,
                    'discount_cents' => $line->discountCents,
                    'tax_rate_bps' => $line->taxRateBps,
                    'tax_cents' => $line->taxCents(),
                    'total_cents' => $line->totalCents(),
                ]);
            }

            if ($invoice->total_cents === 0) {
                $invoice->forceFill(['status' => InvoiceStatus::Paid])->save();
            }

            $this->audit->log('billing', AuditEvent::InvoiceIssued, $invoice, $actor, [
                'number' => $invoice->number,
                'member_id' => $member->id,
                'total_cents' => $invoice->total_cents,
            ]);

            return $invoice;
        });
    }
}
