<?php

namespace App\Domain\Shared\Enums;

/**
 * Utilidades comunes para enums con etiqueta en español.
 */
trait HasLabel
{
    abstract public function label(): string;

    /**
     * @return array<string|int, string> valor => etiqueta, para selects.
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /**
     * @return list<string|int>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
