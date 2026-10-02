<?php

namespace App\Domain\CheckIns\Enums;

use App\Domain\Shared\Enums\HasLabel;

enum RejectionReason: string
{
    use HasLabel;

    case MembershipExpired = 'membership_expired';
    case MembershipSuspended = 'membership_suspended';
    case MembershipFrozen = 'membership_frozen';
    case MemberInactive = 'member_inactive';
    case LocationNotAllowed = 'location_not_allowed';
    case VisitLimitReached = 'visit_limit_reached';
    case Duplicate = 'duplicate';
    case NotFound = 'not_found';

    /**
     * Texto para el staff.
     */
    public function label(): string
    {
        return match ($this) {
            self::MembershipExpired => 'Sin membresía vigente',
            self::MembershipSuspended => 'Membresía suspendida',
            self::MembershipFrozen => 'Membresía congelada',
            self::MemberInactive => 'Cliente inactivo o bloqueado',
            self::LocationNotAllowed => 'El plan no incluye esta sede',
            self::VisitLimitReached => 'Sin ingresos disponibles en el periodo',
            self::Duplicate => 'Ingreso repetido',
            self::NotFound => 'No identificado',
        };
    }

    /**
     * Texto para el cliente en el kiosco. No revela datos de terceros.
     */
    public function kioskMessage(): string
    {
        return match ($this) {
            self::MembershipExpired => 'No tienes una membresía vigente. Acércate a recepción.',
            self::MembershipSuspended => 'Tu membresía está suspendida por un pago pendiente. Acércate a recepción.',
            self::MembershipFrozen => 'Tu membresía está congelada. Acércate a recepción si quieres reanudarla.',
            self::MemberInactive => 'No podemos registrar tu ingreso. Acércate a recepción.',
            self::LocationNotAllowed => 'Tu plan no incluye esta sede. Acércate a recepción.',
            self::VisitLimitReached => 'Ya usaste todos los ingresos de tu plan en este periodo. Acércate a recepción.',
            self::Duplicate => 'Tu ingreso ya estaba registrado. ¡Buen entrenamiento!',
            self::NotFound => 'No encontramos tus datos. Verifícalos o acércate a recepción.',
        };
    }

    /**
     * Un rechazo que se puede autorizar desde recepción (no aplica a
     * intentos sin identificar ni a repetidos, que ya tienen ingreso).
     */
    public function canBeOverridden(): bool
    {
        return ! in_array($this, [self::NotFound, self::Duplicate], true);
    }
}
