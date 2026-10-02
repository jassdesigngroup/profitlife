<?php

namespace App\Domain\Billing\Models;

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Policies\InvoicePolicy;
use App\Domain\Identity\Models\User;
use App\Domain\Locations\Models\Location;
use App\Domain\Members\Models\Member;
use App\Support\BusinessDate;
use App\Support\Concerns\HasLocationScope;
use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Comprobante interno de cobro (no es factura electrónica). Los precios
 * incluyen IVA: `tax_cents` es la porción de impuesto ya contenida en el total.
 */
#[Fillable([
    'number', 'member_id', 'location_id', 'status', 'issued_at', 'due_on', 'subtotal_cents', 'discount_cents',
    'tax_cents', 'total_cents', 'paid_cents', 'currency', 'notes', 'created_by',
])]
#[UsePolicy(InvoicePolicy::class)]
class Invoice extends Model
{
    use HasLocationScope, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'issued_at' => 'datetime',
            'due_on' => 'date',
            'subtotal_cents' => 'integer',
            'discount_cents' => 'integer',
            'tax_cents' => 'integer',
            'total_cents' => 'integer',
            'paid_cents' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class)->withoutGlobalScopes();
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class)->withoutGlobalScopes();
    }

    /**
     * @return HasMany<InvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->withoutGlobalScopes()->latest('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function balanceCents(): int
    {
        return $this->status === InvoiceStatus::Void ? 0 : max(0, $this->total_cents - $this->paid_cents);
    }

    public function money(int $cents): Money
    {
        return Money::ofCents($cents, $this->currency);
    }

    public function isOverdue(): bool
    {
        return $this->status->isOpen() && $this->due_on !== null && $this->due_on->lessThan(BusinessDate::today());
    }

    /**
     * Recalcula lo pagado y el estado a partir de los pagos recibidos.
     */
    public function recalculate(): void
    {
        if ($this->status === InvoiceStatus::Void) {
            return;
        }

        $paid = (int) $this->payments()->where('status', PaymentStatus::Paid)->sum('amount_cents');

        $this->forceFill([
            'paid_cents' => $paid,
            'status' => match (true) {
                $paid >= $this->total_cents => InvoiceStatus::Paid,
                $paid > 0 => InvoiceStatus::PartiallyPaid,
                default => InvoiceStatus::Issued,
            },
        ])->save();
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid]);
    }
}
