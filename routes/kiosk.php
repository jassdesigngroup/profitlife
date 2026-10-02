<?php

use Illuminate\Support\Facades\Route;

/*
| Pantalla del kiosco. Es pública: no muestra datos hasta que el
| dispositivo se vincula y llama a la API con su token.
*/
Route::view('/kiosco', 'kiosk.index')->name('kiosk');
