<?php

namespace App\Domain\Staff\Services;

use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\URL;

/**
 * Enlace firmado y con caducidad para que un empleado defina su contraseña.
 * Incluye un hash del email: si el email cambia, el enlace deja de valer.
 */
class InvitationUrl
{
    public static function for(User $user): string
    {
        return URL::temporarySignedRoute(
            'invitation.show',
            now()->addHours(config('profitlife.invitations.expires_hours')),
            ['user' => $user->getKey(), 'hash' => self::hash($user)],
        );
    }

    public static function hash(User $user): string
    {
        return hash('sha256', $user->getKey().'|'.$user->email);
    }
}
