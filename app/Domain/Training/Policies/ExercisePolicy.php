<?php

namespace App\Domain\Training\Policies;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Training\Models\Exercise;

/**
 * Biblioteca de ejercicios: la ve y amplía quien prescribe entrenamiento.
 */
class ExercisePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::TrainingView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::TrainingCreate->value);
    }

    public function update(User $user, Exercise $exercise): bool
    {
        return $user->can(Permission::TrainingUpdate->value);
    }

    public function delete(User $user, Exercise $exercise): bool
    {
        return $user->can(Permission::TrainingDelete->value);
    }
}
