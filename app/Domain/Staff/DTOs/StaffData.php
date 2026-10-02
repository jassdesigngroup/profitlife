<?php

namespace App\Domain\Staff\DTOs;

use App\Domain\Identity\Enums\RoleName;
use App\Domain\Shared\Enums\DocumentType;

/**
 * Datos ya validados para crear o editar un empleado.
 */
final readonly class StaffData
{
    /**
     * @param  list<RoleName>  $roles
     * @param  list<int>  $locationIds
     */
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $email,
        public ?DocumentType $documentType = null,
        public ?string $documentNumber = null,
        public ?string $phone = null,
        public ?string $jobTitle = null,
        public ?string $professionalLicense = null,
        public ?string $calendarColor = null,
        public bool $isBookable = false,
        public ?string $hiredOn = null,
        public array $roles = [],
        public array $locationIds = [],
        public ?int $primaryLocationId = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function profileAttributes(): array
    {
        return [
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'document_type' => $this->documentType,
            'document_number' => $this->documentNumber,
            'phone' => $this->phone,
            'job_title' => $this->jobTitle,
            'professional_license' => $this->professionalLicense,
            'calendar_color' => $this->calendarColor,
            'is_bookable' => $this->isBookable,
            'hired_on' => $this->hiredOn,
        ];
    }

    public function fullName(): string
    {
        return trim("{$this->firstName} {$this->lastName}");
    }
}
