<?php

namespace App\Domain\Appointments\DTOs;

use App\Domain\Memberships\Models\Membership;
use App\Support\Money;

/**
 * Cómo se paga una cita: sesiones ilimitadas del plan, una sesión del
 * saldo del plan o un comprobante por la sesión suelta.
 */
final readonly class Coverage
{
    public const UNLIMITED = 'unlimited';

    public const CREDIT = 'credit';

    public const INVOICE = 'invoice';

    public function __construct(
        public string $type,
        public ?Membership $membership,
        public ?int $remaining,
        public int $priceCents,
        public string $currency,
    ) {}

    public function describe(): string
    {
        return match ($this->type) {
            self::UNLIMITED => 'Incluida en el plan '.$this->membership?->plan?->name.' (ilimitadas)',
            self::CREDIT => 'Sesión del plan '.$this->membership?->plan?->name.' (quedan '.$this->remaining.')',
            default => 'Sesión suelta: se genera un comprobante por '.Money::ofCents($this->priceCents, $this->currency)->format(),
        };
    }
}
