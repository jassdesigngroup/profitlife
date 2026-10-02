<?php

namespace App\Domain\Memberships\Enums;

use App\Domain\Shared\Enums\HasLabel;

enum MembershipStatus: string
{
    use HasLabel;

    case Pending = 'pending';
    case Active = 'active';
    case Frozen = 'frozen';
    case Suspended = 'suspended';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Por iniciar',
            self::Active => 'Activa',
            self::Frozen => 'Congelada',
            self::Suspended => 'Suspendida',
            self::Expired => 'Vencida',
            self::Cancelled => 'Cancelada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Pending => 'info',
            self::Frozen => 'brand',
            self::Suspended => 'danger',
            self::Expired, self::Cancelled => 'neutral',
        };
    }

    /**
     * Estados de una membresía vigente: ocupan el calendario del cliente y
     * lo hacen visible en las sedes donde el plan es válido.
     *
     * @return list<self>
     */
    public static function current(): array
    {
        return [self::Pending, self::Active, self::Frozen, self::Suspended];
    }

    /**
     * @return list<string>
     */
    public static function currentValues(): array
    {
        return array_map(fn (self $s) => $s->value, self::current());
    }

    public function isCurrent(): bool
    {
        return in_array($this, self::current(), true);
    }
}
