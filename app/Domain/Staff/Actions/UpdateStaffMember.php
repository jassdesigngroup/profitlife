<?php

namespace App\Domain\Staff\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Staff\DTOs\StaffData;
use App\Domain\Staff\Models\Staff;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UpdateStaffMember
{
    public function __construct(
        private readonly SyncStaffRoles $syncRoles,
        private readonly SyncStaffLocations $syncLocations,
    ) {}

    /**
     * @param  bool  $syncRoles  false cuando el actor no puede gestionar roles
     */
    public function execute(Staff $staff, StaffData $data, User $actor, bool $syncRoles = true): Staff
    {
        return DB::transaction(function () use ($staff, $data, $actor, $syncRoles) {
            $user = $staff->user;
            $email = Str::lower($data->email);

            if ($email !== $user->email && User::withTrashed()->where('email', $email)->whereKeyNot($user->id)->exists()) {
                throw ValidationException::withMessages(['email' => 'Este correo ya está en uso.']);
            }

            $staff->fill($data->profileAttributes())->save();
            $user->fill(['name' => $data->fullName(), 'email' => $email])->save();

            $this->syncLocations->execute($staff, $data->locationIds, $data->primaryLocationId, $actor);

            if ($syncRoles) {
                $this->syncRoles->execute($staff, $data->roles, $actor);
            }

            return $staff->refresh();
        });
    }
}
