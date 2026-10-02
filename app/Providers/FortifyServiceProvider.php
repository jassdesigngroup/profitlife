<?php

namespace App\Providers;

use App\Domain\Identity\Actions\AuthenticateUser;
use App\Domain\Identity\Actions\DisableTwoFactorAuthentication;
use App\Domain\Identity\Actions\ResetUserPassword;
use App\Domain\Identity\Actions\UpdateUserPassword;
use App\Http\Admin\Responses\GenericPasswordResetLinkResponse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication as FortifyDisableTwoFactorAuthentication;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FortifyDisableTwoFactorAuthentication::class, DisableTwoFactorAuthentication::class);

        // Misma respuesta exista o no el email: no se revela qué cuentas existen.
        $this->app->bind(FailedPasswordResetLinkRequestResponse::class, GenericPasswordResetLinkResponse::class);
        $this->app->bind(SuccessfulPasswordResetLinkRequestResponse::class, GenericPasswordResetLinkResponse::class);
    }

    public function boot(): void
    {
        Fortify::authenticateUsing(new AuthenticateUser);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        Fortify::loginView(fn () => view('admin.auth.login'));
        Fortify::requestPasswordResetLinkView(fn () => view('admin.auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('admin.auth.reset-password', ['request' => $request]));
        Fortify::twoFactorChallengeView(fn () => view('admin.auth.two-factor-challenge'));
        Fortify::confirmPasswordView(fn () => view('admin.auth.confirm-password'));

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }
}
