<?php

namespace App\Domain\Locations\Actions;

use App\Domain\Locations\Models\Room;

/**
 * Borrado lógico: las citas históricas (Fase 6) seguirán apuntando a la sala.
 */
class DeleteRoom
{
    public function execute(Room $room): void
    {
        $room->delete();
    }
}
