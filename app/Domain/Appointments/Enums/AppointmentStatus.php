<?php

namespace App\Domain\Appointments\Enums;

use App\Domain\Shared\Enums\HasLabel;

enum AppointmentStatus: string
{
    use HasLabel;

    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';
    case Rescheduled = 'rescheduled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Confirmed => 'Confirmada',
            self::Completed => 'Atendida',
            self::Cancelled => 'Cancelada',
            self::NoShow => 'No asistió',
            self::Rescheduled => 'Reprogramada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Confirmed => 'info',
            self::Completed => 'success',
            self::Cancelled, self::NoShow => 'danger',
            self::Rescheduled => 'neutral',
        };
    }

    /**
     * Estados que ocupan la agenda (cuentan para los cruces).
     *
     * @return list<self>
     */
    public static function active(): array
    {
        return [self::Pending, self::Confirmed];
    }

    /**
     * @return list<string>
     */
    public static function activeValues(): array
    {
        return array_map(fn (self $s) => $s->value, self::active());
    }

    public function isActive(): bool
    {
        return in_array($this, self::active(), true);
    }
}
