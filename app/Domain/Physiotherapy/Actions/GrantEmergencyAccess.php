<?php

namespace App\Domain\Physiotherapy\Actions;

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Models\User;
use App\Domain\Physiotherapy\Enums\ClinicalAction;
use App\Domain\Physiotherapy\Models\PhysiotherapyRecord;
use App\Domain\Physiotherapy\Notifications\EmergencyAccessNotification;
use App\Domain\Physiotherapy\Services\ClinicalAccess;
use Illuminate\Validation\ValidationException;

/**
 * "Romper el vidrio": abre la historia por unas horas a quien tiene el
 * permiso de emergencia, con motivo obligatorio. Queda en la bitácora y en
 * la auditoría, y se avisa al profesional responsable.
 */
class GrantEmergencyAccess
{
    public function __construct(
        private readonly ClinicalAccess $access,
        private readonly AuditLogger $audit,
    ) {}

    public function execute(PhysiotherapyRecord $record, User $actor, string $reason): void
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < 10) {
            throw ValidationException::withMessages(['emergencyReason' => 'Explique el motivo (mínimo 10 caracteres).']);
        }

        $this->access->log($actor, $record->member_id, $record, ClinicalAction::Emergency);
        $this->audit->log('clinical', AuditEvent::ClinicalEmergencyAccess, $record, $actor, [
            'member_id' => $record->member_id,
            'reason' => $reason,
            'hours' => ClinicalAccess::EMERGENCY_HOURS,
        ]);

        $primary = $record->primaryStaff?->user;
        if ($primary !== null && $primary->id !== $actor->id) {
            $primary->notify(new EmergencyAccessNotification($record, $actor, $reason));
        }
    }
}
