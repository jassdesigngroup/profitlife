<?php

namespace App\Livewire\Admin\Concerns;

/**
 * Los montos se digitan en pesos enteros ("150.000" o "150000") y se guardan
 * en centavos.
 */
trait ParsesMoney
{
    protected function pesosToCents(string|int|null $value): int
    {
        $digits = preg_replace('/\D/', '', (string) $value);

        return $digits === '' ? 0 : ((int) $digits) * 100;
    }

    protected function centsToPesos(?int $cents): string
    {
        return $cents === null ? '' : (string) intdiv($cents, 100);
    }
}
