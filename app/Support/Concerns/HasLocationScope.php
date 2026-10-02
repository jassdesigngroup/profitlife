<?php

namespace App\Support\Concerns;

use App\Support\Scopes\LocationScope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Para modelos que pertenecen a una o varias sedes. Añade el global scope
 * LocationScope y expone las sedes del registro para las Policies.
 *
 * Por defecto filtra por la columna `location_id`; un modelo puede cambiar
 * la columna (locationScopeColumn) o la consulta completa
 * (applyLocationRestriction) y las sedes del registro (locationIds).
 */
trait HasLocationScope
{
    public static function bootHasLocationScope(): void
    {
        static::addGlobalScope(new LocationScope);
    }

    public function locationScopeColumn(): string
    {
        return 'location_id';
    }

    /**
     * @param  Builder<static>  $query
     * @param  list<int>  $locationIds
     */
    public function applyLocationRestriction(Builder $query, array $locationIds): void
    {
        $query->whereIn($this->qualifyColumn($this->locationScopeColumn()), $locationIds);
    }

    /**
     * Sedes a las que pertenece este registro.
     *
     * @return list<int>
     */
    public function locationIds(): array
    {
        return [(int) $this->getAttribute($this->locationScopeColumn())];
    }

    /**
     * Filtro opcional de la interfaz (selector de sede). No es una barrera de
     * seguridad: esa la ponen LocationScope y las Policies.
     *
     * @param  Builder<static>  $query
     */
    public function scopeInLocation(Builder $query, ?int $locationId): void
    {
        if ($locationId !== null) {
            $this->applyLocationRestriction($query, [$locationId]);
        }
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeWithoutLocationScope(Builder $query): void
    {
        $query->withoutGlobalScope(LocationScope::class);
    }
}
