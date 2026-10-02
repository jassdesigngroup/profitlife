<?php

namespace App\Domain\Members\Actions;

use App\Domain\Members\Enums\MemberStatus;
use App\Domain\Members\Models\Member;

/**
 * Activo, inactivo o bloqueado. El cambio queda en la auditoría del modelo.
 */
class ChangeMemberStatus
{
    public function execute(Member $member, MemberStatus $status): Member
    {
        $member->update(['status' => $status]);

        return $member;
    }
}
