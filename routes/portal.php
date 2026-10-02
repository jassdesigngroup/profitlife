<?php

use App\Http\Portal\Controllers\AccessCodeController;
use Illuminate\Support\Facades\Route;

/*
| Portal de clientes. Por ahora solo la página del código de acceso, a la
| que se llega con el enlace firmado que se envía por correo.
*/
Route::get('/mi-acceso/{credential}', AccessCodeController::class)
    ->whereNumber('credential')
    ->middleware(['signed', 'throttle:30,1'])
    ->name('access-code.show');
