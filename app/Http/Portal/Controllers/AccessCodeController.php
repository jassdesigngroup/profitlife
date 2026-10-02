<?php

namespace App\Http\Portal\Controllers;

use App\Domain\CheckIns\Models\MemberAccessCredential;
use App\Support\QrCode;
use Illuminate\View\View;

/**
 * Página pública (enlace firmado del correo) con el código QR del cliente.
 */
class AccessCodeController
{
    public function __invoke(int $credential): View
    {
        $credential = MemberAccessCredential::query()->withoutGlobalScopes()->with('member')->find($credential);
        $usable = $credential !== null && $credential->isUsable() && $credential->member !== null;

        return view('portal.access-code', [
            'firstName' => $usable ? $credential->member->first_name : null,
            'memberNumber' => $usable ? $credential->member->member_number : null,
            'qr' => $usable ? QrCode::svg($credential->token_encrypted, 300) : null,
        ]);
    }
}
