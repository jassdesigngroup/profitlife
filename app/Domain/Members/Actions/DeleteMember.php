<?php

namespace App\Domain\Members\Actions;

use App\Domain\Members\Models\Member;
use App\Domain\Physiotherapy\Models\PhysiotherapyRecord;
use Illuminate\Validation\ValidationException;

/**
 * Borrado lógico: el historial (consentimientos, documentos, notas) se conserva.
 */
class DeleteMember
{
    public function execute(Member $member): void
    {
        // La historia clínica se conserva por ley: el paciente no se elimina.
        if (PhysiotherapyRecord::query()->where('member_id', $member->id)->exists()) {
            throw ValidationException::withMessages(['delete' => 'El cliente tiene historia clínica y no se puede eliminar. Puede inactivarlo.']);
        }

        $member->delete();
    }
}
