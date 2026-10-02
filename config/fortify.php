<?php

use Laravel\Fortify\Features;

return [

    'guard' => 'web',

    'passwords' => 'users',

    'username' => 'email',

    'email' => 'email',

    'lowercase_usernames' => true,

    // Destino tras iniciar sesión. Por ahora solo existe el panel administrativo.
    'home' => '/admin',

    'prefix' => '',

    'domain' => null,

    'middleware' => ['web'],

    /*
    | Con `login` en null, Fortify aplica su propio limitador de intentos
    | fallidos (5 por minuto por email + IP) y dispara el evento Lockout,
    | que queda registrado en la auditoría.
    */
    'limiters' => [
        'login' => null,
        'two-factor' => 'two-factor',
    ],

    'views' => true,

    /*
    | Sin registro público: el staff entra por invitación. Sin edición de
    | perfil por Fortify: los datos los gestiona el módulo de Staff.
    */
    'features' => [
        Features::resetPasswords(),
        Features::updatePasswords(),
        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]),
    ],

];
