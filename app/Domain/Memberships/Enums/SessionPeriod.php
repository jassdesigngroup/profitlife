<?php

namespace App\Domain\Memberships\Enums;

use App\Domain\Shared\Enums\HasLabel;

/**
 * Cada cuánto se otorgan las sesiones incluidas en un plan. Mientras el
 * cobro coincida con la duración del plan, ambos se otorgan al vender o
 * renovar cada periodo.
 */
enum SessionPeriod: string
{
    use HasLabel;

    case PerBillingPeriod = 'per_billing_period';
    case PerTerm = 'per_term';

    public function label(): string
    {
        return match ($this) {
            self::PerBillingPeriod => 'por periodo de cobro',
            self::PerTerm => 'en toda la vigencia',
        };
    }
}
