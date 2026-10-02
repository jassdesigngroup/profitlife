<?php

namespace App\Domain\Audit\Enums;

use App\Domain\Shared\Enums\HasLabel;

/**
 * Eventos explícitos de auditoría. Los cambios de modelos (created,
 * updated, deleted) los registra activitylog con su propio nombre.
 */
enum AuditEvent: string
{
    use HasLabel;

    case Login = 'login';
    case Logout = 'logout';
    case LoginFailed = 'login_failed';
    case Lockout = 'lockout';
    case PasswordReset = 'password_reset';
    case PasswordUpdated = 'password_updated';
    case TwoFactorEnabled = 'two_factor_enabled';
    case TwoFactorDisabled = 'two_factor_disabled';
    case TwoFactorFailed = 'two_factor_failed';
    case RecoveryCodesRegenerated = 'recovery_codes_regenerated';
    case InvitationSent = 'invitation_sent';
    case InvitationAccepted = 'invitation_accepted';
    case RolesUpdated = 'roles_updated';
    case PermissionsUpdated = 'permissions_updated';
    case LocationsAssigned = 'locations_assigned';
    case HoursUpdated = 'hours_updated';
    case StatusChanged = 'status_changed';
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Restored = 'restored';

    public function label(): string
    {
        return match ($this) {
            self::Login => 'Inicio de sesión',
            self::Logout => 'Cierre de sesión',
            self::LoginFailed => 'Intento fallido de inicio de sesión',
            self::Lockout => 'Bloqueo por intentos fallidos',
            self::PasswordReset => 'Contraseña restablecida',
            self::PasswordUpdated => 'Contraseña cambiada',
            self::TwoFactorEnabled => '2FA activado',
            self::TwoFactorDisabled => '2FA desactivado',
            self::TwoFactorFailed => 'Código 2FA incorrecto',
            self::RecoveryCodesRegenerated => 'Códigos de recuperación regenerados',
            self::InvitationSent => 'Invitación enviada',
            self::InvitationAccepted => 'Invitación aceptada',
            self::RolesUpdated => 'Roles modificados',
            self::PermissionsUpdated => 'Permisos modificados',
            self::LocationsAssigned => 'Sedes asignadas',
            self::HoursUpdated => 'Horarios modificados',
            self::StatusChanged => 'Estado cambiado',
            self::Created => 'Creación',
            self::Updated => 'Modificación',
            self::Deleted => 'Eliminación',
            self::Restored => 'Restauración',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::LoginFailed, self::Lockout, self::TwoFactorFailed, self::Deleted, self::TwoFactorDisabled => 'danger',
            self::RolesUpdated, self::PermissionsUpdated, self::StatusChanged => 'warning',
            self::Created, self::Login, self::InvitationAccepted, self::TwoFactorEnabled => 'success',
            default => 'neutral',
        };
    }

    /**
     * Descripción en español para los eventos de modelo de activitylog.
     */
    public static function describeModelEvent(string $entity, string $eventName): string
    {
        $event = self::tryFrom($eventName);

        return $event === null ? "{$eventName} {$entity}" : "{$event->label()} de {$entity}";
    }

    public static function logNameLabel(?string $logName): string
    {
        return match ($logName) {
            'auth' => 'Autenticación',
            'users' => 'Usuarios',
            'staff' => 'Staff',
            'locations' => 'Sedes',
            'roles' => 'Roles y permisos',
            'settings' => 'Ajustes',
            default => (string) $logName,
        };
    }
}
