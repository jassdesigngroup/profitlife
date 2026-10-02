<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\User;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication as FortifyDisableTwoFactorAuthentication;

/**
 * Impide que un usuario con un rol que exige 2FA lo desactive por su cuenta.
 */
class DisableTwoFactorAuthentication extends FortifyDisableTwoFactorAuthentication
{
    public function __invoke($user)
    {
        if ($user instanceof User && $user->requiresTwoFactor()) {
            throw ValidationException::withMessages([
                'two_factor' => 'Su rol exige la verificación en dos pasos; no se puede desactivar.',
            ])->errorBag('disableTwoFactorAuthentication');
        }

        parent::__invoke($user);
    }
}
