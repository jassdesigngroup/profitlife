<?php

namespace App\Http\Admin\Controllers;

use App\Domain\CheckIns\Enums\CredentialType;
use App\Domain\CheckIns\Models\MemberAccessCredential;
use App\Domain\Members\Models\Member;
use App\Support\QrCode;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Tarjeta imprimible con el código QR de acceso. Mostrar el código permite
 * entrar como el cliente, así que exige poder editarlo.
 */
class MemberAccessCardController
{
    public function __invoke(Member $member): View
    {
        Gate::authorize('update', $member);

        $credential = MemberAccessCredential::query()
            ->where('member_id', $member->id)
            ->where('type', CredentialType::Qr)
            ->usable()
            ->latest('id')
            ->firstOrFail();

        return view('admin.members.access-card', [
            'member' => $member,
            'qr' => QrCode::svg($credential->token_encrypted, 260),
        ]);
    }
}
