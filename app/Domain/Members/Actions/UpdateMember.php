<?php

namespace App\Domain\Members\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Members\DTOs\MemberData;
use App\Domain\Members\Models\Member;
use App\Support\Scopes\LocationScope;
use Illuminate\Validation\ValidationException;

class UpdateMember
{
    public function __construct(private readonly EnsureLocationInScope $ensureLocation) {}

    public function execute(Member $member, MemberData $data, User $actor): Member
    {
        if ($data->homeLocationId !== $member->home_location_id) {
            // Mover al cliente a otra sede exige tener alcance sobre la sede destino.
            $this->ensureLocation->execute($data->homeLocationId, $actor);
        }

        if ($data->documentType !== null && $data->documentNumber !== null) {
            $taken = Member::query()->withoutGlobalScope(LocationScope::class)->withTrashed()
                ->where('document_type', $data->documentType)
                ->where('document_number', $data->documentNumber)
                ->whereKeyNot($member->id)
                ->exists();

            if ($taken) {
                throw ValidationException::withMessages(['document_number' => 'Ya existe un cliente con este documento.']);
            }
        }

        $member->update($data->toAttributes() + array_filter(['joined_on' => $data->joinedOn]));

        return $member;
    }
}
