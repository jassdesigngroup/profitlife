<?php

namespace App\Domain\Audit\Listeners;

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Str;
use Laravel\Fortify\Events\PasswordUpdatedViaController;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;

/**
 * Auditoría de autenticación. Los intentos fallidos guardan la IP y, si la
 * cuenta existe, su id como sujeto; nunca el email ni la contraseña tecleados.
 */
class AuthenticationActivitySubscriber
{
    private const LOG = 'auth';

    public function __construct(private readonly AuditLogger $audit) {}

    public function handleLogin(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $event->user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
        ])->saveQuietly();

        $this->audit->log(self::LOG, AuditEvent::Login, $event->user, $event->user, ['remember' => $event->remember]);
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user instanceof User) {
            $this->audit->log(self::LOG, AuditEvent::Logout, $event->user, $event->user);
        }
    }

    public function handleFailed(Failed $event): void
    {
        $user = $event->user instanceof User ? $event->user : $this->findUser($event->credentials['email'] ?? null);

        $this->audit->log(self::LOG, AuditEvent::LoginFailed, $user, null, ['account_exists' => $user !== null]);
    }

    public function handleLockout(Lockout $event): void
    {
        $user = $this->findUser($event->request->input('email'));

        $this->audit->log(self::LOG, AuditEvent::Lockout, $user, null, ['account_exists' => $user !== null]);
    }

    public function handlePasswordReset(PasswordReset $event): void
    {
        if ($event->user instanceof User) {
            $this->audit->log(self::LOG, AuditEvent::PasswordReset, $event->user, $event->user);
        }
    }

    public function handlePasswordUpdated(PasswordUpdatedViaController $event): void
    {
        if ($event->user instanceof User) {
            $this->audit->log(self::LOG, AuditEvent::PasswordUpdated, $event->user, $event->user);
        }
    }

    public function handleTwoFactorConfirmed(TwoFactorAuthenticationConfirmed $event): void
    {
        $this->audit->log(self::LOG, AuditEvent::TwoFactorEnabled, $event->user, $event->user);
    }

    public function handleTwoFactorDisabled(TwoFactorAuthenticationDisabled $event): void
    {
        $this->audit->log(self::LOG, AuditEvent::TwoFactorDisabled, $event->user, $event->user);
    }

    public function handleTwoFactorFailed(TwoFactorAuthenticationFailed $event): void
    {
        $this->audit->log(self::LOG, AuditEvent::TwoFactorFailed, $event->user, null);
    }

    public function handleRecoveryCodes(RecoveryCodesGenerated $event): void
    {
        $this->audit->log(self::LOG, AuditEvent::RecoveryCodesRegenerated, $event->user, $event->user);
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'handleLogin',
            Logout::class => 'handleLogout',
            Failed::class => 'handleFailed',
            Lockout::class => 'handleLockout',
            PasswordReset::class => 'handlePasswordReset',
            PasswordUpdatedViaController::class => 'handlePasswordUpdated',
            TwoFactorAuthenticationConfirmed::class => 'handleTwoFactorConfirmed',
            TwoFactorAuthenticationDisabled::class => 'handleTwoFactorDisabled',
            TwoFactorAuthenticationFailed::class => 'handleTwoFactorFailed',
            RecoveryCodesGenerated::class => 'handleRecoveryCodes',
        ];
    }

    private function findUser(mixed $email): ?User
    {
        if (! is_string($email) || $email === '') {
            return null;
        }

        return User::query()->where('email', Str::lower($email))->first();
    }
}
