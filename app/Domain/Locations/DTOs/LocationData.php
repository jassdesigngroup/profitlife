<?php

namespace App\Domain\Locations\DTOs;

final readonly class LocationData
{
    public function __construct(
        public string $name,
        public string $slug,
        public string $code,
        public string $addressLine,
        public string $city,
        public string $department,
        public ?string $phone,
        public ?string $email,
        public string $timezone,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'code' => strtoupper($this->code),
            'address_line' => $this->addressLine,
            'city' => $this->city,
            'department' => $this->department,
            'phone' => $this->phone,
            'email' => $this->email === null ? null : strtolower($this->email),
            'timezone' => $this->timezone,
        ];
    }
}
