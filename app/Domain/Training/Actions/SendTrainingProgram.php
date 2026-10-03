<?php

namespace App\Domain\Training\Actions;

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Models\User;
use App\Domain\Training\Models\TrainingProgram;
use App\Domain\Training\Notifications\TrainingProgramNotification;
use Illuminate\Validation\ValidationException;

class SendTrainingProgram
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function execute(TrainingProgram $program, User $actor): void
    {
        $member = $program->member;

        if ($program->is_template || $member === null || blank($member->email)) {
            throw ValidationException::withMessages(['send' => 'El cliente no tiene correo registrado.']);
        }

        $member->notify(new TrainingProgramNotification($program));

        $this->audit->log('training', AuditEvent::ProgramSent, $program, $actor, ['member_id' => $member->id]);
    }
}
