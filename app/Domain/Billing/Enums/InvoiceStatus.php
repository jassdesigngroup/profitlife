<?php

namespace App\Domain\Billing\Enums;

use App\Domain\Shared\Enums\HasLabel;

enum InvoiceStatus: string
{
    use HasLabel;

    case Draft = 'draft';
    case Issued = 'issued';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Issued => 'Pendiente de pago',
            self::PartiallyPaid => 'Abonado',
            self::Paid => 'Pagado',
            self::Void => 'Anulado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Paid => 'success',
            self::PartiallyPaid => 'warning',
            self::Issued => 'danger',
            self::Draft, self::Void => 'neutral',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Issued, self::PartiallyPaid], true);
    }
}
