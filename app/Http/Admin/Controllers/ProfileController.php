<?php

namespace App\Http\Admin\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Perfil propio: datos de la cuenta, cambio de contraseña (Fortify) y
 * estado de la verificación en dos pasos.
 */
class ProfileController
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        return view('admin.profile.show', [
            'user' => $user,
            'staff' => $user->staff?->load('locations'),
        ]);
    }
}
