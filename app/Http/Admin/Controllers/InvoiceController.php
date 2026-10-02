<?php

namespace App\Http\Admin\Controllers;

use App\Domain\Billing\Models\Invoice;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Comprobante imprimible. La ruta resuelve con LocationScope y la Policy
 * vuelve a validar la sede.
 */
class InvoiceController
{
    public function __invoke(Invoice $invoice): View
    {
        Gate::authorize('view', $invoice);

        return view('admin.invoices.show', [
            'invoice' => $invoice->load(['items', 'member', 'location', 'payments.receiver:id,name', 'creator:id,name']),
        ]);
    }
}
