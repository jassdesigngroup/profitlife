<?php

namespace App\Domain\Physiotherapy\Policies;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Members\Models\Member;
use App\Domain\Physiotherapy\Models\PhysiotherapyRecord;
use App\Domain\Physiotherapy\Services\ClinicalAccess;

/**
 * Historia clínica. El contenido lo ve solo el equipo tratante (o un acceso
 * de emergencia); recepción, gerencia y administración ven los datos
 * administrativos (que existe, quién atiende, fechas de sesiones).
 */
class PhysiotherapyRecordPolicy
{
    public function __construct(private readonly ClinicalAccess $access) {}

    /**
     * Resumen administrativo del expediente de un cliente.
     */
    public function viewSummary(User $user, Member $member): bool
    {
        return $user->can('view', $member)
            && ($user->can(Permission::ClinicalNotesView->value) || $user->can(Permission::AppointmentsViewAll->value));
    }

    public function open(User $user, Member $member): bool
    {
        return $user->can(Permission::ClinicalNotesCreate->value)
            && $user->staff !== null
            && $user->can('view', $member)
            && ! PhysiotherapyRecord::query()->where('member_id', $member->id)->exists();
    }

    public function view(User $user, PhysiotherapyRecord $record): bool
    {
        return $this->access->canViewContent($user, $record);
    }

    /**
     * Registrar sesiones, notas y planes: solo el equipo (el acceso de
     * emergencia es de solo lectura).
     */
    public function write(User $user, PhysiotherapyRecord $record): bool
    {
        return $user->can(Permission::ClinicalNotesCreate->value) && $this->access->isOnTeam($user, $record);
    }

    public function update(User $user, PhysiotherapyRecord $record): bool
    {
        return $user->can(Permission::ClinicalNotesUpdate->value) && $this->access->isOnTeam($user, $record);
    }

    /**
     * Agregar o retirar profesionales: el responsable o quien gestiona staff.
     */
    public function manageTeam(User $user, PhysiotherapyRecord $record): bool
    {
        $member = $record->member;

        return $member !== null
            && $user->can('view', $member)
            && ($user->staff?->id === $record->primary_staff_id || $user->can(Permission::StaffUpdate->value));
    }

    public function export(User $user, PhysiotherapyRecord $record): bool
    {
        return $user->can(Permission::ClinicalNotesExport->value) && $this->access->canViewContent($user, $record);
    }

    public function emergency(User $user, PhysiotherapyRecord $record): bool
    {
        return $user->can(Permission::ClinicalNotesEmergency->value) && ! $this->access->isOnTeam($user, $record);
    }
}
