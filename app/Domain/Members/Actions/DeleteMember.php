<?php

namespace App\Domain\Members\Actions;

use App\Domain\Members\Models\Member;

/**
 * Borrado lógico: el historial (consentimientos, documentos, notas) se conserva.
 */
class DeleteMember
{
    public function execute(Member $member): void
    {
        $member->delete();
    }
}
