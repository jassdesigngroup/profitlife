<?php

namespace App\Support\Locations;

use App\Domain\Identity\Models\User;
use App\Domain\Locations\Models\Location;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Auth;

/**
 * Selector "Sede / Todas las sedes" de la barra superior.
 *
 * Es solo un filtro de sesión para la interfaz: nunca concede acceso. Al
 * leerlo se vuelve a comprobar que el usuario tiene acceso a esa sede.
 */
class CurrentLocation
{
    public const SESSION_KEY = 'admin.current_location_id';

    public function __construct(private readonly Session $session) {}

    public function id(): ?int
    {
        $id = $this->session->get(self::SESSION_KEY);
        $user = Auth::user();

        if ($id === null || ! $user instanceof User || ! $user->canAccessLocation((int) $id)) {
            return null;
        }

        return (int) $id;
    }

    public function location(): ?Location
    {
        $id = $this->id();

        return $id === null ? null : Location::query()->find($id);
    }

    public function set(?int $locationId): void
    {
        $user = Auth::user();

        if ($locationId === null || ! $user instanceof User || ! $user->canAccessLocation($locationId)) {
            $this->session->forget(self::SESSION_KEY);

            return;
        }

        $this->session->put(self::SESSION_KEY, $locationId);
    }
}
