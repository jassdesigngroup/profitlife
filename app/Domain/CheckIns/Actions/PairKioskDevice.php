<?php

namespace App\Domain\CheckIns\Actions;

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\CheckIns\Models\KioskDevice;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Emite el token del kiosco (se muestra una sola vez) y revoca los
 * anteriores: un enlace de vinculación viejo deja de funcionar.
 */
class PairKioskDevice
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function execute(KioskDevice $device, User $actor): string
    {
        return DB::transaction(function () use ($device, $actor) {
            $device->tokens()->delete();
            $token = $device->createToken(KioskDevice::TOKEN_NAME, [KioskDevice::ABILITY]);

            $this->audit->log('check_ins', AuditEvent::KioskPaired, $device, $actor, [
                'location_id' => $device->location_id,
            ]);

            return $token->plainTextToken;
        });
    }
}
