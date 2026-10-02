<?php

namespace App\Domain\Staff\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Asignación de un empleado a una sede. Define su alcance de datos.
 */
class LocationStaff extends Pivot
{
    protected $table = 'location_staff';

    public $incrementing = true;

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }
}
