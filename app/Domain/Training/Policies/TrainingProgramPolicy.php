<?php

namespace App\Domain\Training\Policies;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Members\Models\Member;
use App\Domain\Physiotherapy\Models\PhysiotherapyRecord;
use App\Domain\Training\Enums\ProgramType;
use App\Domain\Training\Models\TrainingProgram;

/**
 * Programas de entrenamiento: los ve y edita quien prescribe y puede ver al
 * cliente (sus sedes). Los de rehabilitación siguen la historia clínica:
 * solo el equipo tratante.
 */
class TrainingProgramPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::TrainingView->value);
    }

    public function view(User $user, TrainingProgram $program): bool
    {
        if (! $user->can(Permission::TrainingView->value)) {
            return false;
        }

        if ($program->is_template) {
            return true;
        }

        $member = $program->member;

        return $member !== null
            && $user->can('view', $member)
            && (! $program->isRehab() || $this->clinical($user, $member, 'view'));
    }

    /**
     * Crear un programa para un cliente (o una plantilla, sin cliente).
     */
    public function create(User $user, ?Member $member = null, ProgramType $type = ProgramType::Training): bool
    {
        if (! $user->can(Permission::TrainingCreate->value) || $user->staff === null) {
            return false;
        }

        if ($member === null) {
            return $type === ProgramType::Training;
        }

        return $user->can('view', $member)
            && ($type === ProgramType::Training || $this->clinical($user, $member, 'write'));
    }

    public function update(User $user, TrainingProgram $program): bool
    {
        return $user->can(Permission::TrainingUpdate->value)
            && $this->view($user, $program)
            && (! $program->isRehab() || $this->clinical($user, $program->member, 'write'));
    }

    public function delete(User $user, TrainingProgram $program): bool
    {
        return $user->can(Permission::TrainingDelete->value) && $this->update($user, $program);
    }

    /**
     * Registrar un entrenamiento realizado.
     */
    public function log(User $user, TrainingProgram $program): bool
    {
        return ! $program->is_template && $this->update($user, $program);
    }

    private function clinical(User $user, ?Member $member, string $ability): bool
    {
        $record = $member ? PhysiotherapyRecord::query()->where('member_id', $member->id)->first() : null;

        return $record !== null && $user->can($ability, $record);
    }
}
