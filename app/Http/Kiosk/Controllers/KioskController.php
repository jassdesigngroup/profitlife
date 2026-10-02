<?php

namespace App\Http\Kiosk\Controllers;

use App\Domain\Billing\Models\Invoice;
use App\Domain\CheckIns\Actions\RegisterCheckIn;
use App\Domain\CheckIns\DTOs\CheckInOutcome;
use App\Domain\CheckIns\Enums\CheckInMethod;
use App\Domain\CheckIns\Models\KioskDevice;
use App\Domain\CheckIns\Services\KioskMemberResolver;
use App\Domain\Settings\Services\Settings;
use App\Http\Kiosk\Requests\KioskCheckInRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API del kiosco. Responde solo lo que el cliente frente a la pantalla
 * necesita ver: su nombre de pila, el resultado y avisos sin montos.
 */
class KioskController
{
    public function show(Request $request, Settings $settings): JsonResponse
    {
        /** @var KioskDevice $device */
        $device = $request->user();

        return response()->json([
            'device' => $device->name,
            'location' => $device->location->name,
            'brand' => $settings->brandName(),
        ]);
    }

    public function checkIn(KioskCheckInRequest $request, KioskMemberResolver $resolver, RegisterCheckIn $register): JsonResponse
    {
        /** @var KioskDevice $device */
        $device = $request->user();
        $value = (string) $request->validated('value');

        $method = CheckInMethod::from($request->validated('method'));

        if ($method === CheckInMethod::Phone && $resolver->phoneIsLocked($value)) {
            return response()->json([
                'status' => 'rejected',
                'title' => 'Demasiados intentos',
                'message' => 'Espera unos minutos o acércate a recepción.',
                'notices' => [],
            ], 429);
        }

        $member = match ($method) {
            CheckInMethod::Qr => $resolver->byQr($value),
            CheckInMethod::MemberNumber => $resolver->byMemberNumber($value),
            CheckInMethod::Phone => $resolver->byPhoneAndPin($value, (string) $request->validated('pin')),
        };

        $outcome = $register->execute($device->location, $method, $member, $device);

        return response()->json($this->present($outcome));
    }

    /**
     * @return array{status: string, title: string, message: string, notices: list<string>}
     */
    private function present(CheckInOutcome $outcome): array
    {
        $name = $outcome->member?->first_name;

        if ($outcome->accepted() || $outcome->isDuplicate()) {
            return [
                'status' => 'accepted',
                'title' => $name ? "¡Hola, {$name}!" : '¡Bienvenido!',
                'message' => $outcome->isDuplicate() ? $outcome->reason()->kioskMessage() : 'Ingreso registrado. ¡Buen entrenamiento!',
                'notices' => $outcome->accepted() ? $this->notices($outcome) : [],
            ];
        }

        return [
            'status' => 'rejected',
            'title' => $name ? "{$name}, no pudimos registrar tu ingreso" : 'No pudimos registrar tu ingreso',
            'message' => $outcome->reason()->kioskMessage(),
            'notices' => [],
        ];
    }

    /**
     * @return list<string>
     */
    private function notices(CheckInOutcome $outcome): array
    {
        $notices = [];

        if ($outcome->visitsLeft !== null) {
            $notices[] = $outcome->visitsLeft === 0
                ? 'Este fue tu último ingreso disponible del periodo.'
                : "Te quedan {$outcome->visitsLeft} ingresos en el periodo.";
        }

        $days = $outcome->membership?->daysRemaining();
        if ($days !== null && $days <= 5) {
            $notices[] = 'Tu membresía vence el '.$outcome->membership->ends_on->format('d/m/Y').'.';
        }

        $owes = Invoice::query()->withoutGlobalScopes()->where('member_id', $outcome->member->id)->open()->exists();
        if ($owes) {
            $notices[] = 'Tienes un pago pendiente. Acércate a recepción.';
        }

        return $notices;
    }
}
