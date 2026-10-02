<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcceptInvitation
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function execute(User $user, #[\SensitiveParameter] string $password): void
    {
        DB::transaction(function () use ($user, $password) {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($user->hasAcceptedInvitation()) {
                throw ValidationException::withMessages(['password' => 'Esta invitación ya fue utilizada.']);
            }

            $user->forceFill([
                'password' => $password,
                'email_verified_at' => now(),
            ])->save();

            $this->audit->log('auth', AuditEvent::InvitationAccepted, $user, $user);
        });
    }
}
