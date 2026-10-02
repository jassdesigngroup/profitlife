<?php

namespace App\Domain\Billing\DTOs;

/**
 * Renglón de un comprobante. El precio incluye IVA.
 */
final readonly class InvoiceLine
{
    public function __construct(
        public string $description,
        public int $unitPriceCents,
        public int $quantity = 1,
        public int $discountCents = 0,
        public int $taxRateBps = 0,
        public ?string $billableType = null,
        public ?int $billableId = null,
    ) {}

    public function grossCents(): int
    {
        return $this->unitPriceCents * $this->quantity;
    }

    public function totalCents(): int
    {
        return max(0, $this->grossCents() - $this->discountCents);
    }

    /**
     * Porción de IVA contenida en el total (precio con IVA incluido).
     */
    public function taxCents(): int
    {
        if ($this->taxRateBps <= 0) {
            return 0;
        }

        $total = $this->totalCents();

        return $total - (int) round($total * 10000 / (10000 + $this->taxRateBps));
    }
}
