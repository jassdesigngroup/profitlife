<?php

namespace App\Domain\Billing\Events;

use App\Domain\Billing\Models\Invoice;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * El comprobante quedó pagado por completo.
 */
class InvoiceSettled
{
    use Dispatchable;

    public function __construct(public readonly Invoice $invoice) {}
}
