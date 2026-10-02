<?php

namespace App\Domain\Identity\Models;

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Enums\RoleName;
use App\Domain\Identity\Notifications\ResetPasswordNotification;
use App\Domain\Members\Models\Member;
use App\Domain\Staff\Models\Staff;
use App\Support\Scopes\LocationScope;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

/**
 * Solo autenticación. Los datos de negocio están en Staff (y, desde la
 * Fase 3, en Member); una misma persona puede tener ambos perfiles.
 */
#[Fillable(['name', 'email', 'password', 'is_active', 'locale'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
#[UseFactory(UserFactory::class)]
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, LogsActivity, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

    /** @var list<int>|null */
    protected ?array $accessibleLocationIdsCache = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    /**
     * @return HasOne<Staff, $this>
     */
    public function staff(): HasOne
    {
        return $this->hasOne(Staff::class)->withoutGlobalScope(LocationScope::class);
    }

    /**
     * Perfil de cliente, si lo tiene (una persona puede ser staff y cliente).
     *
     * @return HasOne<Member, $this>
     */
    public function member(): HasOne
    {
        return $this->hasOne(Member::class)->withoutGlobalScope(LocationScope::class);
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(RoleName::SuperAdmin->value);
    }

    public function hasAcceptedInvitation(): bool
    {
        return $this->password !== null;
    }

    public function hasConfirmedTwoFactor(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    /**
     * Super Admin, Administrador, Gerente de sede y Fisioterapeuta deben
     * tener 2FA confirmado para entrar al panel.
     */
    public function requiresTwoFactor(): bool
    {
        foreach ($this->roleEnums() as $role) {
            if ($role->requiresTwoFactor()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<RoleName>
     */
    public function roleEnums(): array
    {
        return $this->getRoleNames()
            ->map(fn (string $name) => RoleName::tryFrom($name))
            ->filter()
            ->values()
            ->all();
    }

    public function canAccessAllLocations(): bool
    {
        return $this->checkPermissionTo(Permission::LocationsViewAll->value);
    }

    /**
     * Sedes asignadas al usuario por medio de su perfil de staff.
     *
     * Se consulta sin Eloquent para no disparar el propio LocationScope.
     *
     * @return list<int>
     */
    public function accessibleLocationIds(): array
    {
        return $this->accessibleLocationIdsCache ??= DB::table('location_staff')
            ->join('staff', 'staff.id', '=', 'location_staff.staff_id')
            ->join('locations', 'locations.id', '=', 'location_staff.location_id')
            ->where('staff.user_id', $this->getKey())
            ->whereNull('staff.deleted_at')
            ->whereNull('locations.deleted_at')
            ->orderBy('location_staff.location_id')
            ->pluck('location_staff.location_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function canAccessLocation(int $locationId): bool
    {
        return $this->canAccessAllLocations() || in_array($locationId, $this->accessibleLocationIds(), true);
    }

    /**
     * @param  iterable<int>  $locationIds
     */
    public function canAccessAnyLocation(iterable $locationIds): bool
    {
        if ($this->canAccessAllLocations()) {
            return true;
        }

        foreach ($locationIds as $id) {
            if (in_array((int) $id, $this->accessibleLocationIds(), true)) {
                return true;
            }
        }

        return false;
    }

    public function flushLocationAccess(): void
    {
        $this->accessibleLocationIdsCache = null;
    }

    public function initials(): string
    {
        return collect(explode(' ', trim($this->name)))
            ->filter()
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('users')
            ->logOnly(['name', 'email', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $event) => AuditEvent::describeModelEvent('usuario', $event));
    }
}
