<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Valida credenciales para Fortify. Devuelve null (mensaje genérico) si el
 * usuario no existe, no ha aceptado su invitación, está inactivo o la
 * contraseña no coincide; así no se revela qué cuentas existen.
 */
class AuthenticateUser
{
    public function __invoke(Request $request): ?User
    {
        $user = User::query()->where('email', Str::lower((string) $request->input('email')))->first();

        if (! $user || $user->password === null || ! $user->is_active) {
            return null;
        }

        return Hash::check((string) $request->input('password'), $user->password) ? $user : null;
    }
}
