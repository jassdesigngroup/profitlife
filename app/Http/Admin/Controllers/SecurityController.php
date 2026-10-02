<?php

namespace App\Http\Admin\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Configuración de la verificación en dos pasos (TOTP). Exige haber
 * confirmado la contraseña recientemente; los formularios envían a las
 * rutas de Fortify.
 */
class SecurityController
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $pending = $user->two_factor_secret !== null && $user->two_factor_confirmed_at === null;

        return view('admin.profile.security', [
            'user' => $user,
            'pending' => $pending,
            'qrCode' => $pending ? $user->twoFactorQrCodeSvg() : null,
            'setupKey' => $pending ? decrypt($user->two_factor_secret) : null,
            // Los códigos solo se muestran justo después de confirmarlos o regenerarlos.
            'recoveryCodes' => $user->hasConfirmedTwoFactor()
                && in_array(session('status'), ['two-factor-authentication-confirmed', 'recovery-codes-generated'], true)
                ? $user->recoveryCodes()
                : [],
        ]);
    }
}
