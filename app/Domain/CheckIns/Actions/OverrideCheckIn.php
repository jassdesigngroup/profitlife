<?php

namespace App\Domain\CheckIns\Actions;

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\CheckIns\Enums\CheckInMethod;
use App\Domain\CheckIns\Enums\CheckInResult;
use App\Domain\CheckIns\Models\CheckIn;
use App\Domain\Identity\Models\User;
use App\Domain\Members\Models\Member;
use App\Domain\Settings\Services\Settings;
use App\Support\BusinessDate;
use App\Support\Scopes\LocationScope;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Autoriza el ingreso de un cliente que fue rechazado. Crea un ingreso
 * aceptado nuevo (el rechazo se conserva) y lo deja en la auditoría con el
 * motivo. La Policy CheckIn@override la aplica quien llama.
 */
class OverrideCheckIn
{
    public function __construct(
        private readonly RegisterCheckIn $register,
        private readonly AuditLogger $audit,
        private readonly Settings $settings,
    ) {}

    public function execute(CheckIn $rejected, string $reason, User $actor): CheckIn
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['overrideReason' => 'Indique el motivo de la autorización.']);
        }

        $tz = $this->settings->displayTimezone();
        if ($rejected->checked_in_at->copy()->setTimezone($tz)->format('Y-m-d') !== BusinessDate::today()->toDateString()) {
            throw ValidationException::withMessages(['overrideReason' => 'Solo se pueden autorizar rechazos del día.']);
        }

        return DB::transaction(function () use ($rejected, $reason, $actor) {
            $member = Member::query()->withoutGlobalScope(LocationScope::class)->lockForUpdate()->findOrFail($rejected->member_id);

            if ($this->register->recentAccepted($member, Date::now()) !== null) {
                throw ValidationException::withMessages(['overrideReason' => 'El cliente ya tiene un ingreso registrado.']);
            }

            $checkIn = CheckIn::query()->create([
                'member_id' => $member->id,
                'location_id' => $rejected->location_id,
                'membership_id' => $rejected->membership_id,
                'registered_by' => $actor->id,
                'method' => CheckInMethod::Manual,
                'result' => CheckInResult::Accepted,
                'checked_in_at' => Date::now(),
            ]);

            $this->audit->log('check_ins', AuditEvent::CheckInOverridden, $checkIn, $actor, [
                'member_id' => $member->id,
                'location_id' => $rejected->location_id,
                'rejected_check_in_id' => $rejected->id,
                'rejection_reason' => $rejected->rejection_reason?->value,
                'reason' => $reason,
            ]);

            return $checkIn;
        });
    }
}
