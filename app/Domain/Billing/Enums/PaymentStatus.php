<?php

namespace App\Domain\Billing\Enums;

use App\Domain\Shared\Enums\HasLabel;

enum PaymentStatus: string
{
    use HasLabel;

    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Refunded = 'refunded';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Paid => 'Recibido',
            self::Failed => 'Fallido',
            self::Refunded => 'Reembolsado',
            self::Cancelled => 'Anulado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Paid => 'success',
            self::Pending => 'warning',
            self::Failed, self::Cancelled => 'danger',
            self::Refunded => 'neutral',
        };
    }
}
