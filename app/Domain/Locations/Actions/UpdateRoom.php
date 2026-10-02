<?php

namespace App\Domain\Locations\Actions;

use App\Domain\Locations\DTOs\RoomData;
use App\Domain\Locations\Models\Room;

class UpdateRoom
{
    public function execute(Room $room, RoomData $data): Room
    {
        $room->update($data->toAttributes());

        return $room;
    }
}
