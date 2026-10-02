<?php

namespace App\Domain\Locations\Actions;

use App\Domain\Locations\DTOs\RoomData;
use App\Domain\Locations\Models\Location;
use App\Domain\Locations\Models\Room;

class CreateRoom
{
    public function execute(Location $location, RoomData $data): Room
    {
        return $location->rooms()->create($data->toAttributes());
    }
}
