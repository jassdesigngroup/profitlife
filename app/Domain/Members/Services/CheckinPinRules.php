<?php

namespace App\Domain\Members\Services;

use App\Domain\Members\Models\Member;

/**
 * Reglas del PIN de check-in: 4 dígitos y no fácil de adivinar.
 */
class CheckinPinRules
{
    public const LENGTH = 4;

    /**
     * Devuelve el motivo de rechazo o null si el PIN es aceptable.
     */
    public function problem(string $pin, Member $member): ?string
    {
        if (! preg_match('/^\d{'.self::LENGTH.'}$/', $pin)) {
            return 'El PIN debe tener exactamente '.self::LENGTH.' dígitos.';
        }

        if (count(array_unique(str_split($pin))) === 1) {
            return 'El PIN no puede repetir el mismo dígito.';
        }

        if (str_contains('0123456789', $pin) || str_contains('9876543210', $pin)) {
            return 'El PIN no puede ser una secuencia (1234, 4321…).';
        }

        if (preg_match('/^(\d\d)\1$/', $pin)) {
            return 'El PIN no puede ser un patrón repetido (1212…).';
        }

        $phoneDigits = preg_replace('/\D/', '', (string) $member->phone);
        if (strlen($phoneDigits) >= self::LENGTH && str_ends_with($phoneDigits, $pin)) {
            return 'El PIN no puede ser el final del teléfono.';
        }

        if ($member->birth_date !== null && in_array($pin, [$member->birth_date->format('Y'), $member->birth_date->format('dm'), $member->birth_date->format('md')], true)) {
            return 'El PIN no puede ser parte de la fecha de nacimiento.';
        }

        return null;
    }
}
