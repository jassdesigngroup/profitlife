<?php

namespace App\Domain\Locations\DTOs;

use App\Domain\Locations\Enums\RoomType;

final readonly class RoomData
{
    public function __construct(
        public string $name,
        public ?RoomType $type,
        public int $capacity,
        public bool $isActive = true,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'capacity' => $this->capacity,
            'is_active' => $this->isActive,
        ];
    }
}
