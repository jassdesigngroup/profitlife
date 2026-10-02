<?php

namespace App\Domain\Physiotherapy\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Physiotherapy\Models\PhysiotherapyRecord;
use App\Domain\Physiotherapy\Models\PhysiotherapyRecordStaff;
use App\Domain\Staff\Models\Staff;
use Illuminate\Support\Facades\Date;

/**
 * Altas y bajas del equipo tratante. Volver a agregar a alguien retirado
 * reabre su acceso desde ese momento.
 */
class ClinicalTeam
{
    /**
     * @return bool true si cambió algo
     */
    public function add(PhysiotherapyRecord $record, Staff $staff, User $grantedBy): bool
    {
        $row = PhysiotherapyRecordStaff::query()
            ->where('physiotherapy_record_id', $record->id)
            ->where('staff_id', $staff->id)
            ->first();

        if ($row !== null && $row->revoked_at === null) {
            return false;
        }

        $attributes = ['granted_by' => $grantedBy->id, 'granted_at' => Date::now(), 'revoked_at' => null];

        $row === null
            ? PhysiotherapyRecordStaff::query()->create(['physiotherapy_record_id' => $record->id, 'staff_id' => $staff->id] + $attributes)
            : $row->forceFill($attributes)->save();

        return true;
    }

    public function revoke(PhysiotherapyRecord $record, Staff $staff): bool
    {
        return PhysiotherapyRecordStaff::query()
            ->where('physiotherapy_record_id', $record->id)
            ->where('staff_id', $staff->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => Date::now()]) > 0;
    }
}
