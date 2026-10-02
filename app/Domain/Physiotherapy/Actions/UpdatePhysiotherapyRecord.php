<?php

namespace App\Domain\Physiotherapy\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Physiotherapy\Enums\ClinicalAction;
use App\Domain\Physiotherapy\Enums\RecordStatus;
use App\Domain\Physiotherapy\Models\PhysiotherapyRecord;
use App\Domain\Physiotherapy\Services\ClinicalAccess;

/**
 * Antecedentes del expediente (motivo, historia, medicamentos, alergias) y
 * estado (en tratamiento / alta).
 */
class UpdatePhysiotherapyRecord
{
    public function __construct(private readonly ClinicalAccess $access) {}

    /**
     * @param  array{reason_for_consultation: ?string, medical_history: ?string, medications: ?string, allergies: ?string, status: RecordStatus}  $data
     */
    public function execute(PhysiotherapyRecord $record, User $actor, array $data): void
    {
        $record->forceFill([
            'reason_for_consultation' => $data['reason_for_consultation'] ?: null,
            'medical_history' => $data['medical_history'] ?: null,
            'medications' => $data['medications'] ?: null,
            'allergies' => $data['allergies'] ?: null,
            'status' => $data['status'],
        ])->save();

        $this->access->log($actor, $record->member_id, $record, ClinicalAction::Update);
    }
}
