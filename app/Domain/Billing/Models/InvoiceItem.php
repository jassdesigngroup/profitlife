<?php

namespace App\Domain\Billing\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'invoice_id', 'billable_type', 'billable_id', 'description', 'quantity', 'unit_price_cents', 'discount_cents',
    'tax_rate_bps', 'tax_cents', 'total_cents',
])]
#[WithoutTimestamps]
class InvoiceItem extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_cents' => 'integer',
            'discount_cents' => 'integer',
            'tax_rate_bps' => 'integer',
            'tax_cents' => 'integer',
            'total_cents' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class)->withoutGlobalScopes();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function billable(): MorphTo
    {
        return $this->morphTo();
    }
}
