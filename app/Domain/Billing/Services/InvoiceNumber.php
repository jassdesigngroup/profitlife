<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\Invoice;
use App\Domain\Locations\Models\Location;
use App\Support\Scopes\LocationScope;

/**
 * Consecutivo por sede: {código}-{000001}. Debe llamarse dentro de una
 * transacción: bloquea la fila de la sede para que dos recepciones no
 * obtengan el mismo número.
 */
class InvoiceNumber
{
    public function next(Location $location): string
    {
        Location::query()->withoutGlobalScope(LocationScope::class)->whereKey($location->id)->lockForUpdate()->first();

        $prefix = strtoupper($location->code).'-';

        $last = Invoice::query()->withoutGlobalScopes()
            ->where('location_id', $location->id)
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('number');

        $next = $last === null ? 1 : ((int) substr($last, strlen($prefix))) + 1;

        return $prefix.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }
}
