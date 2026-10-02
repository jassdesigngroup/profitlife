<?php

namespace App\Domain\Members\Actions;

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Models\User;
use App\Domain\Members\DTOs\MemberData;
use App\Domain\Members\Models\EmergencyContact;
use App\Domain\Members\Models\Member;
use Illuminate\Support\Facades\DB;

/**
 * Crea o actualiza un contacto de emergencia. Solo uno puede ser principal.
 */
class SaveEmergencyContact
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{name: string, relationship: ?string, phone: string, alt_phone: ?string, email: ?string, is_primary: bool}  $data
     */
    public function execute(Member $member, array $data, ?EmergencyContact $contact, User $actor): EmergencyContact
    {
        return DB::transaction(function () use ($member, $data, $contact, $actor) {
            $attributes = [
                'name' => $data['name'],
                'relationship' => $data['relationship'],
                'phone' => MemberData::normalizePhone($data['phone']),
                'alt_phone' => MemberData::normalizePhone($data['alt_phone']),
                'email' => $data['email'] === null ? null : mb_strtolower($data['email']),
                'is_primary' => $data['is_primary'] || ! $member->emergencyContacts()->whereKeyNot($contact?->id)->exists(),
            ];

            if ($attributes['is_primary']) {
                $member->emergencyContacts()->whereKeyNot($contact?->id)->update(['is_primary' => false]);
            }

            $contact === null
                ? $contact = $member->emergencyContacts()->create($attributes)
                : $contact->update($attributes);

            $this->audit->log('members', AuditEvent::ContactSaved, $member, $actor, ['contact_id' => $contact->id]);

            return $contact;
        });
    }
}
