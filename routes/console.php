<?php

use Illuminate\Support\Facades\Schedule;

/*
| En hosting compartido (cPanel) no hay supervisor para mantener vivo un
| trabajador de colas. El cron del servidor ejecuta `schedule:run` cada
| minuto y este procesa la cola (invitaciones, correos) hasta vaciarla.
| Con un supervisor disponible, basta con `php artisan queue:work`.
*/
Schedule::command('queue:work --stop-when-empty --max-time=55 --tries=3')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->when(fn () => config('queue.default') === 'database');

Schedule::command('auth:clear-resets')->daily();
Schedule::command('queue:prune-failed --hours=720')->daily();
