<?php

namespace App\Domain\CheckIns\DTOs;

use App\Domain\CheckIns\Enums\RejectionReason;
use App\Domain\CheckIns\Models\CheckIn;
use App\Domain\Members\Models\Member;
use App\Domain\Memberships\Models\Membership;

/**
 * Resultado de un intento de ingreso, con los avisos para recepción.
 */
final readonly class CheckInOutcome
{
    /**
     * @param  list<string>  $warnings
     */
    public function __construct(
        public CheckIn $checkIn,
        public ?Member $member,
        public ?Membership $membership,
        public array $warnings = [],
        public ?int $visitsLeft = null,
    ) {}

    public function accepted(): bool
    {
        return $this->checkIn->isAccepted();
    }

    public function reason(): ?RejectionReason
    {
        return $this->checkIn->rejection_reason;
    }

    /**
     * El cliente ya tenía un ingreso reciente: puede pasar.
     */
    public function isDuplicate(): bool
    {
        return $this->reason() === RejectionReason::Duplicate;
    }
}
