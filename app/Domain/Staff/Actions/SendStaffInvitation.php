<?php

namespace App\Domain\Staff\Actions;

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Models\User;
use App\Domain\Staff\Models\Staff;
use App\Domain\Staff\Notifications\StaffInvitationNotification;
use Illuminate\Validation\ValidationException;

class SendStaffInvitation
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function execute(Staff $staff, ?User $actor): void
    {
        $user = $staff->user;

        if ($user->hasAcceptedInvitation()) {
            throw ValidationException::withMessages(['invitation' => 'Este usuario ya definió su contraseña.']);
        }

        $user->notify(new StaffInvitationNotification($user));

        $this->audit->log('staff', AuditEvent::InvitationSent, $staff, $actor);
    }
}
