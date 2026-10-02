<?php

namespace App\Domain\Members\Services;

use App\Domain\Settings\Services\Settings;

/**
 * Número visible del cliente: prefijo configurable + id con ceros (PL-000123).
 * Se deriva del id autoincremental, así que nunca se repite aunque dos
 * recepciones creen clientes a la vez.
 */
class MemberNumber
{
    public function __construct(private readonly Settings $settings) {}

    public function for(int $memberId): string
    {
        $prefix = strtoupper(trim($this->settings->memberNumberPrefix()));
        $number = str_pad((string) $memberId, (int) config('profitlife.members.number_padding'), '0', STR_PAD_LEFT);

        return $prefix === '' ? $number : "{$prefix}-{$number}";
    }
}
