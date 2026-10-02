<?php

namespace App\Domain\Billing\Enums;

use App\Domain\Shared\Enums\HasLabel;

enum PaymentMethod: string
{
    use HasLabel;

    case Cash = 'cash';
    case CardTerminal = 'card_terminal';
    case Transfer = 'transfer';
    case BreB = 'bre_b';
    case Nequi = 'nequi';
    case Pse = 'pse';
    case Gateway = 'gateway';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Efectivo',
            self::CardTerminal => 'Datáfono',
            self::Transfer => 'Transferencia',
            self::BreB => 'Bre-B',
            self::Nequi => 'Nequi',
            self::Pse => 'PSE',
            self::Gateway => 'Pasarela en línea',
        };
    }

    /**
     * Medios que recepción registra a mano (la pasarela llega en la Fase 10).
     *
     * @return array<string, string>
     */
    public static function manualOptions(): array
    {
        $options = self::options();
        unset($options[self::Gateway->value]);

        return $options;
    }

    public function needsReference(): bool
    {
        return in_array($this, [self::Transfer, self::BreB, self::Nequi, self::Pse, self::CardTerminal], true);
    }
}
