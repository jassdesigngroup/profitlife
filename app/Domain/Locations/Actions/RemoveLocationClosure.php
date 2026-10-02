<?php

namespace App\Domain\Locations\Actions;

use App\Domain\Locations\Models\LocationClosure;

class RemoveLocationClosure
{
    public function execute(LocationClosure $closure): void
    {
        $closure->delete();
    }
}
