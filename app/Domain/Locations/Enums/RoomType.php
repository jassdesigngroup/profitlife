<?php

namespace App\Domain\Locations\Enums;

use App\Domain\Shared\Enums\HasLabel;

enum RoomType: string
{
    use HasLabel;

    case ConsultingRoom = 'consulting_room';
    case Room = 'room';
    case Zone = 'zone';

    public function label(): string
    {
        return match ($this) {
            self::ConsultingRoom => 'Consultorio',
            self::Room => 'Sala',
            self::Zone => 'Zona',
        };
    }
}
