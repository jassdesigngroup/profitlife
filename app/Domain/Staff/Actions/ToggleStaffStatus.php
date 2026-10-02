<?php

namespace App\Domain\Staff\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Staff\Enums\StaffStatus;
use App\Domain\Staff\Models\Staff;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Activa o desactiva al empleado y su acceso. Un usuario desactivado no
 * puede iniciar sesión y su sesión abierta se cierra en la siguiente petición.
 */
class ToggleStaffStatus
{
    public function execute(Staff $staff, User $actor): Staff
    {
        if ($staff->user_id === $actor->id) {
            throw new AuthorizationException('No puede desactivar su propia cuenta.');
        }

        return DB::transaction(function () use ($staff) {
            $activate = ! $staff->isActive();

            $staff->update(['status' => $activate ? StaffStatus::Active : StaffStatus::Inactive]);
            $staff->user->update(['is_active' => $activate]);

            if (! $activate) {
                // Invalida "recordarme" para que no vuelva a entrar con la cookie.
                $staff->user->forceFill(['remember_token' => null])->saveQuietly();
            }

            return $staff;
        });
    }
}
