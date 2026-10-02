<?php

namespace App\Domain\Appointments\Policies;

use App\Domain\Appointments\Models\Appointment;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Locations\Models\Location;
use App\Domain\Members\Models\Member;
use App\Domain\Staff\Models\Staff;

/**
 * Recepción y gerencia (appointments.view-all) ven y gestionan la agenda de
 * todos en sus sedes; fisioterapeutas y entrenadores, solo la propia.
 */
class AppointmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::AppointmentsView->value);
    }

    public function view(User $user, Appointment $appointment): bool
    {
        return $user->can(Permission::AppointmentsView->value)
            && $user->canAccessLocation($appointment->location_id)
            && $this->ownsOrSeesAll($user, $appointment->staff_id);
    }

    /**
     * Agendar en una sede, opcionalmente con un profesional y para un cliente.
     */
    public function create(User $user, Location $location, ?Staff $staff = null, ?Member $member = null): bool
    {
        return $user->can(Permission::AppointmentsCreate->value)
            && $user->canAccessLocation($location->id)
            && ($staff === null || $this->ownsOrSeesAll($user, $staff->id))
            && ($member === null || $user->can('view', $member));
    }

    /**
     * Reprogramar y marcar atendida o inasistencia.
     */
    public function update(User $user, Appointment $appointment): bool
    {
        return $user->can(Permission::AppointmentsUpdate->value) && $this->view($user, $appointment);
    }

    public function cancel(User $user, Appointment $appointment): bool
    {
        return $user->can(Permission::AppointmentsCancel->value)
            && $this->view($user, $appointment)
            && $appointment->isActive();
    }

    /**
     * Devolver la sesión descontada por una cancelación tardía o inasistencia.
     */
    public function refundCredit(User $user, Appointment $appointment): bool
    {
        return $user->can(Permission::SessionCreditsAdjust->value)
            && $user->canAccessLocation($appointment->location_id)
            && $appointment->creditsUsed() > 0
            && ! $appointment->isActive();
    }

    private function ownsOrSeesAll(User $user, int $staffId): bool
    {
        return $user->can(Permission::AppointmentsViewAll->value) || $user->staff?->id === $staffId;
    }
}
