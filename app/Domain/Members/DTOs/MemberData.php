<?php

namespace App\Domain\Members\DTOs;

use App\Domain\Members\Enums\Gender;
use App\Domain\Shared\Enums\DocumentType;

/**
 * Datos ya validados de un cliente.
 */
final readonly class MemberData
{
    public function __construct(
        public int $homeLocationId,
        public string $firstName,
        public string $lastName,
        public ?DocumentType $documentType = null,
        public ?string $documentNumber = null,
        public ?string $birthDate = null,
        public ?Gender $gender = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $addressLine = null,
        public ?string $city = null,
        public ?string $department = null,
        public ?string $joinedOn = null,
    ) {}

    /**
     * Deja solo dígitos (y un + inicial) para poder buscar por teléfono en el check-in.
     */
    public static function normalizePhone(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $normalized = preg_replace('/(?!^\+)[^\d]/', '', trim($phone));

        return $normalized === '' ? null : $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'home_location_id' => $this->homeLocationId,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'document_type' => $this->documentType,
            'document_number' => $this->documentNumber,
            'birth_date' => $this->birthDate,
            'gender' => $this->gender,
            'email' => $this->email === null ? null : mb_strtolower($this->email),
            'phone' => self::normalizePhone($this->phone),
            'address_line' => $this->addressLine,
            'city' => $this->city,
            'department' => $this->department,
        ];
    }
}
