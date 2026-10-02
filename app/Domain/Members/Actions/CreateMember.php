<?php

namespace App\Domain\Members\Actions;

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Enums\RoleName;
use App\Domain\Identity\Models\User;
use App\Domain\Members\DTOs\MemberData;
use App\Domain\Members\Enums\MemberStatus;
use App\Domain\Members\Models\Member;
use App\Domain\Members\Services\MemberNumber;
use App\Support\Scopes\LocationScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Facades\Activity;

/**
 * Registra un cliente con su número visible. Si su correo pertenece a un
 * miembro del staff sin perfil de cliente, se enlaza a ese mismo usuario
 * (una persona puede ser staff y cliente).
 */
class CreateMember
{
    public function __construct(
        private readonly EnsureLocationInScope $ensureLocation,
        private readonly MemberNumber $numbers,
        private readonly AuditLogger $audit,
    ) {}

    public function execute(MemberData $data, User $actor): Member
    {
        $this->ensureLocation->execute($data->homeLocationId, $actor);
        $this->assertDocumentIsFree($data);

        return DB::transaction(function () use ($data, $actor) {
            $member = Activity::withoutLogs(function () use ($data, $actor) {
                $member = Member::query()->create($data->toAttributes() + [
                    // Temporal y único: el número definitivo depende del id.
                    'member_number' => 'TMP'.Str::lower(Str::random(17)),
                    'status' => MemberStatus::Active,
                    'joined_on' => $data->joinedOn ?? now()->toDateString(),
                    'created_by' => $actor->id,
                ]);

                $member->forceFill(['member_number' => $this->numbers->for($member->id)])->save();

                return $member;
            });

            $this->linkStaffUser($member);

            $this->audit->log('members', AuditEvent::Created, $member, $actor, [
                'attributes' => $member->only(['member_number', 'first_name', 'last_name', 'home_location_id', 'status', 'user_id']),
            ], AuditEvent::describeModelEvent('cliente', 'created'));

            return $member->refresh();
        });
    }

    private function assertDocumentIsFree(MemberData $data): void
    {
        if ($data->documentType === null || $data->documentNumber === null) {
            return;
        }

        $exists = Member::query()->withoutGlobalScope(LocationScope::class)->withTrashed()
            ->where('document_type', $data->documentType)
            ->where('document_number', $data->documentNumber)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['document_number' => 'Ya existe un cliente con este documento.']);
        }
    }

    private function linkStaffUser(Member $member): void
    {
        if ($member->email === null) {
            return;
        }

        $user = User::query()->where('email', $member->email)->first();

        if ($user === null || $user->staff === null || $user->member !== null) {
            return;
        }

        $member->forceFill(['user_id' => $user->id])->saveQuietly();
        $user->assignRole(RoleName::Member->value);
    }
}
