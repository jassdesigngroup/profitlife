<?php

namespace App\Support;

use InvalidArgumentException;
use NumberFormatter;
use Stringable;

/**
 * Valor monetario inmutable en centavos (unidad menor) más su moneda ISO 4217.
 */
final class Money implements Stringable
{
    private function __construct(
        public readonly int $cents,
        public readonly string $currency,
    ) {
        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException("Moneda no válida: {$currency}");
        }
    }

    public static function ofCents(int $cents, ?string $currency = null): self
    {
        return new self($cents, strtoupper($currency ?? config('profitlife.currency')));
    }

    /**
     * Crea un valor a partir de unidades mayores (p. ej. pesos). Redondea al centavo.
     */
    public static function of(int|float|string $amount, ?string $currency = null): self
    {
        if (! is_numeric($amount)) {
            throw new InvalidArgumentException('El monto debe ser numérico.');
        }

        return self::ofCents((int) round(((float) $amount) * 100), $currency);
    }

    public static function zero(?string $currency = null): self
    {
        return self::ofCents(0, $currency);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->cents + $other->cents, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->cents - $other->cents, $this->currency);
    }

    public function multiply(int $factor): self
    {
        return new self($this->cents * $factor, $this->currency);
    }

    /**
     * Aplica una tasa en puntos básicos (1900 = 19 %), redondeando al centavo.
     */
    public function percentageBps(int $bps): self
    {
        return new self((int) round($this->cents * $bps / 10000), $this->currency);
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency && $this->cents === $other->cents;
    }

    public function isZero(): bool
    {
        return $this->cents === 0;
    }

    public function isNegative(): bool
    {
        return $this->cents < 0;
    }

    public function format(?string $locale = null): string
    {
        $formatter = new NumberFormatter($locale ?? config('profitlife.locale'), NumberFormatter::CURRENCY);
        $hasDecimals = $this->cents % 100 !== 0;
        $formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, $hasDecimals ? 2 : 0);

        return (string) $formatter->formatCurrency($this->cents / 100, $this->currency);
    }

    public function __toString(): string
    {
        return $this->format();
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException("No se pueden operar {$this->currency} y {$other->currency}.");
        }
    }
}
