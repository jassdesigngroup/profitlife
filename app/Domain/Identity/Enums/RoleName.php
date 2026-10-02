<?php

namespace App\Domain\Identity\Enums;

use App\Domain\Identity\Models\User;
use App\Domain\Shared\Enums\HasLabel;

/**
 * Roles globales del sistema. El alcance por sede no se modela con roles
 * sino con la tabla `location_staff`.
 */
enum RoleName: string
{
    use HasLabel;

    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case LocationManager = 'location_manager';
    case Reception = 'reception';
    case Physiotherapist = 'physiotherapist';
    case Trainer = 'trainer';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Admin => 'Administrador',
            self::LocationManager => 'Gerente de sede',
            self::Reception => 'Recepción',
            self::Physiotherapist => 'Fisioterapeuta',
            self::Trainer => 'Entrenador',
            self::Member => 'Cliente',
        };
    }

    /**
     * Roles que exigen 2FA confirmado para entrar al panel.
     */
    public function requiresTwoFactor(): bool
    {
        return in_array($this, [self::SuperAdmin, self::Admin, self::LocationManager, self::Physiotherapist], true);
    }

    /**
     * Roles que se asignan desde el módulo de Staff (el de Cliente llega con
     * el módulo de clientes).
     *
     * @return list<self>
     */
    public static function staffRoles(): array
    {
        return array_values(array_filter(self::cases(), fn (self $role) => $role !== self::Member));
    }

    /**
     * Roles que un usuario puede asignar o retirar a otros. Solo Super Admin
     * puede asignar Super Admin; un Gerente de sede solo asigna roles
     * operativos de su sede.
     *
     * @return list<self>
     */
    public static function assignableBy(User $actor): array
    {
        if (! $actor->can(Permission::UsersManageRoles->value)) {
            return [];
        }

        if ($actor->hasRole(self::SuperAdmin->value)) {
            return self::staffRoles();
        }

        if ($actor->hasRole(self::Admin->value)) {
            return array_values(array_filter(self::staffRoles(), fn (self $r) => $r !== self::SuperAdmin));
        }

        if ($actor->hasRole(self::LocationManager->value)) {
            return [self::Reception, self::Physiotherapist, self::Trainer];
        }

        return [];
    }
}
