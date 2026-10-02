<?php

use App\Http\Admin\Controllers\AcceptInvitationController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

// Invitación del staff: enlace firmado con caducidad.
Route::middleware(['signed', 'throttle:10,1'])->group(function () {
    Route::get('/invitacion/{user}/{hash}', [AcceptInvitationController::class, 'show'])
        ->whereNumber('user')
        ->name('invitation.show');
    Route::post('/invitacion/{user}/{hash}', [AcceptInvitationController::class, 'store'])
        ->whereNumber('user')
        ->name('invitation.store');
});
