<?php

use App\Http\Kiosk\Controllers\KioskController;
use Illuminate\Support\Facades\Route;

/*
| API del kiosco de check-in. El dispositivo se autentica con su token
| Sanctum (Authorization: Bearer). Sin sesión ni cookies.
*/
Route::prefix('kiosk')
    ->name('api.kiosk.')
    ->middleware(['auth:sanctum', 'kiosk.device', 'throttle:kiosk'])
    ->group(function () {
        Route::get('/me', [KioskController::class, 'show'])->name('show');
        Route::post('/check-ins', [KioskController::class, 'checkIn'])->name('check-ins.store');
    });
