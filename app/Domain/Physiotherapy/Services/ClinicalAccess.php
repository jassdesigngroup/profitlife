<?php

namespace App\Domain\Physiotherapy\Services;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Members\Models\Member;
use App\Domain\Physiotherapy\Enums\ClinicalAction;
use App\Domain\Physiotherapy\Models\ClinicalAccessLog;
use App\Domain\Physiotherapy\Models\PhysiotherapyRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

/**
 * Quién puede ver el contenido clínico y la bitácora de accesos.
 *
 * Ve el contenido el equipo tratante vigente del expediente, o quien tenga
 * un acceso de emergencia abierto (dura EMERGENCY_HOURS y queda registrado).
 */
class ClinicalAccess
{
    public const EMERGENCY_HOURS = 2;

    public function isOnTeam(User $user, PhysiotherapyRecord $record): bool
    {
        return $user->can(Permission::ClinicalNotesView->value) && $record->hasOnTeam($user->staff);
    }

    public function hasEmergencyAccess(User $user, int $memberId): bool
    {
        return ClinicalAccessLog::query()
            ->where('user_id', $user->id)
            ->where('member_id', $memberId)
            ->where('action', ClinicalAction::Emergency)
            ->where('created_at', '>=', Date::now()->subHours(self::EMERGENCY_HOURS))
            ->exists();
    }

    public function emergencyExpiresAt(User $user, int $memberId): ?\DateTimeInterface
    {
        $at = ClinicalAccessLog::query()
            ->where('user_id', $user->id)
            ->where('member_id', $memberId)
            ->where('action', ClinicalAction::Emergency)
            ->latest('id')
            ->value('created_at');

        return $at ? Date::parse($at)->addHours(self::EMERGENCY_HOURS) : null;
    }

    public function canViewContent(User $user, PhysiotherapyRecord $record): bool
    {
        return $this->isOnTeam($user, $record) || $this->hasEmergencyAccess($user, $record->member_id);
    }

    /**
     * Documentos clínicos: con expediente, solo el equipo (o emergencia); sin
     * expediente, quien tenga permisos clínicos y vea al cliente.
     */
    public function canViewClinicalDocuments(User $user, Member $member): bool
    {
        $record = PhysiotherapyRecord::query()->where('member_id', $member->id)->first();

        if ($record === null) {
            return $user->can(Permission::ClinicalNotesView->value) && $user->can('view', $member);
        }

        return $this->canViewContent($user, $record);
    }

    public function log(User $user, int $memberId, Model $subject, ClinicalAction $action): void
    {
        $request = request();

        ClinicalAccessLog::query()->create([
            'user_id' => $user->id,
            'member_id' => $memberId,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'action' => $action,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent() ? Str::limit($request->userAgent(), 250, '') : null,
        ]);
    }
}
