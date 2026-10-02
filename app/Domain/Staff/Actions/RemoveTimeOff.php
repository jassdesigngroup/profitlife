<?php

namespace App\Domain\Staff\Actions;

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Models\User;
use App\Domain\Staff\Models\Staff;
use App\Domain\Staff\Models\StaffTimeOff;

class RemoveTimeOff
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function execute(Staff $staff, StaffTimeOff $off, User $actor): void
    {
        $off->delete();

        $this->audit->log('staff', AuditEvent::TimeOffRemoved, $staff, $actor, ['time_off_id' => $off->id]);
    }
}
