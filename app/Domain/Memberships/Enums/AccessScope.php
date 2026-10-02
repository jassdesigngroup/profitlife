<?php

namespace App\Domain\Memberships\Enums;

use App\Domain\Shared\Enums\HasLabel;

enum AccessScope: string
{
    use HasLabel;

    case AllLocations = 'all_locations';
    case SelectedLocations = 'selected_locations';

    public function label(): string
    {
        return match ($this) {
            self::AllLocations => 'Todas las sedes',
            self::SelectedLocations => 'Sedes seleccionadas',
        };
    }
}
