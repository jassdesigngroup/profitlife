<?php

namespace App\Http\Kiosk\Middleware;

use App\Domain\CheckIns\Models\KioskDevice;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Symfony\Component\HttpFoundation\Response;

/**
 * Después de auth:sanctum: el token debe ser de un kiosco activo, con la
 * habilidad de check-in, en una sede activa. Anota cuándo y desde qué IP
 * se vio por última vez (a lo sumo una vez por minuto).
 */
class AuthenticateKioskDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $device = $request->user();

        if (! $device instanceof KioskDevice
            || ! $device->tokenCan(KioskDevice::ABILITY)
            || ! $device->is_active
            || ! $device->location?->is_active) {
            abort(403, 'Este kiosco no está habilitado.');
        }

        if ($device->last_seen_at === null || $device->last_seen_at->lt(Date::now()->subMinute()) || $device->last_ip !== $request->ip()) {
            $device->forceFill(['last_seen_at' => Date::now(), 'last_ip' => $request->ip()])->saveQuietly();
        }

        return $next($request);
    }
}
