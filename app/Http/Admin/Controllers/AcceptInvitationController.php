<?php

namespace App\Http\Admin\Controllers;

use App\Domain\Identity\Actions\AcceptInvitation;
use App\Domain\Identity\Models\User;
use App\Domain\Staff\Services\InvitationUrl;
use App\Http\Admin\Requests\AcceptInvitationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Página pública del enlace de invitación. El middleware `signed` valida
 * firma y caducidad tanto al mostrar como al enviar el formulario.
 */
class AcceptInvitationController
{
    public function show(Request $request, int $user, string $hash): View|Response
    {
        $invited = $this->resolve($user, $hash);

        if ($invited === null) {
            return response()->view('admin.auth.invitation-invalid', [], 410);
        }

        if (Auth::check()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return view('admin.auth.accept-invitation', [
            'user' => $invited,
            'action' => $request->fullUrl(),
        ]);
    }

    public function store(AcceptInvitationRequest $request, AcceptInvitation $accept, int $user, string $hash): RedirectResponse|Response
    {
        $invited = $this->resolve($user, $hash);

        if ($invited === null) {
            return response()->view('admin.auth.invitation-invalid', [], 410);
        }

        $accept->execute($invited, $request->validated('password'));

        return redirect()->route('login')->with('success', 'Su cuenta está activa. Ya puede iniciar sesión.');
    }

    private function resolve(int $userId, string $hash): ?User
    {
        $user = User::query()->find($userId);

        if ($user === null || ! $user->is_active || $user->hasAcceptedInvitation()) {
            return null;
        }

        return hash_equals(InvitationUrl::hash($user), $hash) ? $user : null;
    }
}
