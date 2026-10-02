<?php

namespace App\Domain\Locations\Actions;

use App\Domain\Locations\Models\Location;

class ToggleLocationStatus
{
    public function execute(Location $location): Location
    {
        $location->update(['is_active' => ! $location->is_active]);

        return $location;
    }
}
