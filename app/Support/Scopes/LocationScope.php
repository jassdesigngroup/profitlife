<?php

namespace App\Support\Scopes;

use App\Domain\Identity\Models\User;
use App\Support\Concerns\HasLocationScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Restringe las consultas a las sedes asignadas al usuario autenticado
 * (tabla `location_staff`). No se aplica a quien tiene `locations.view-all`
 * ni fuera de una petición autenticada (consola, colas).
 *
 * Es la primera barrera; las Policies vuelven a validar cada registro.
 */
class LocationScope implements Scope
{
    /**
     * @param  Builder<Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if (! $user instanceof User || $user->canAccessAllLocations()) {
            return;
        }

        /** @var Model&HasLocationScope $model */
        $model->applyLocationRestriction($builder, $user->accessibleLocationIds());
    }
}
