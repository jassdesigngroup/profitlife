<?php

namespace App\Domain\Staff\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Staff\DTOs\StaffData;
use App\Domain\Staff\Enums\StaffStatus;
use App\Domain\Staff\Models\Staff;
use App\Support\Scopes\LocationScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Crea el usuario (sin contraseña) y su perfil de staff, asigna roles y
 * sedes y envía la invitación en cola.
 *
 * Si ya existe un usuario con ese email sin perfil de staff (p. ej. un
 * cliente), se le añade el perfil: una persona puede ser staff y cliente.
 */
class CreateStaffMember
{
    public function __construct(
        private readonly SyncStaffRoles $syncRoles,
        private readonly SyncStaffLocations $syncLocations,
        private readonly SendStaffInvitation $sendInvitation,
    ) {}

    public function execute(StaffData $data, User $actor): Staff
    {
        $staff = DB::transaction(function () use ($data, $actor) {
            $email = Str::lower($data->email);
            $user = User::withTrashed()->where('email', $email)->lockForUpdate()->first();

            if ($user !== null && ($user->trashed() || Staff::withoutGlobalScope(LocationScope::class)->withTrashed()->where('user_id', $user->id)->exists())) {
                throw ValidationException::withMessages(['email' => 'Ya existe un miembro del staff con este correo.']);
            }

            $user ??= User::query()->create([
                'name' => $data->fullName(),
                'email' => $email,
                'password' => null,
                'is_active' => true,
                'locale' => config('profitlife.locale'),
            ]);

            $staff = Staff::query()->create($data->profileAttributes() + [
                'user_id' => $user->id,
                'status' => StaffStatus::Active,
            ]);

            $this->syncLocations->execute($staff, $data->locationIds, $data->primaryLocationId, $actor);
            $this->syncRoles->execute($staff, $data->roles, $actor);

            return $staff;
        });

        if (! $staff->user->hasAcceptedInvitation()) {
            $this->sendInvitation->execute($staff, $actor);
        }

        return $staff;
    }
}
