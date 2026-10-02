<?php

namespace App\Http\Admin\Middleware;

use App\Domain\Identity\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Los roles que exigen 2FA no entran al panel hasta confirmarlo: se les
 * envía a la página de seguridad para configurarlo.
 */
class EnsureTwoFactorIsConfirmed
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->requiresTwoFactor() && ! $user->hasConfirmedTwoFactor()) {
            if ($request->expectsJson() || $request->hasHeader('X-Livewire')) {
                abort(403, 'Debe configurar la verificación en dos pasos.');
            }

            return redirect()->route('admin.security')
                ->with('warning', 'Su rol exige la verificación en dos pasos. Configúrela para continuar.');
        }

        return $next($request);
    }
}
