<?php

use App\Domain\Identity\Enums\RoleName;
use App\Domain\Identity\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Hash;

it('crea el primer Super Admin desde la consola', function () {
    $this->artisan('profitlife:create-super-admin', ['--first-name' => 'Sofía', '--last-name' => 'Rangel', '--email' => 'Dueña@Example.com'])
        ->expectsQuestion('Contraseña (mín. 10 caracteres, mayúsculas, minúsculas y números)', 'ClaveSegura2026')
        ->expectsQuestion('Repita la contraseña', 'ClaveSegura2026')
        ->assertSuccessful();

    $user = User::query()->where('email', 'dueña@example.com')->sole();
    expect($user->isSuperAdmin())->toBeTrue()
        ->and(Hash::check('ClaveSegura2026', $user->password))->toBeTrue()
        ->and(staffOf($user)->full_name)->toBe('Sofía Rangel')
        ->and($user->canAccessAllLocations())->toBeTrue();

    // Debe configurar 2FA antes de usar el panel.
    $this->actingAs($user)->get('/admin')->assertRedirect(route('admin.security'));
});

it('rechaza una contraseña débil o un correo repetido', function () {
    $existing = User::factory()->create();

    $this->artisan('profitlife:create-super-admin', ['--first-name' => 'A', '--last-name' => 'B', '--email' => 'nuevo@example.com'])
        ->expectsQuestion('Contraseña (mín. 10 caracteres, mayúsculas, minúsculas y números)', 'corta')
        ->expectsQuestion('Repita la contraseña', 'corta')
        ->assertFailed();

    $this->artisan('profitlife:create-super-admin', ['--first-name' => 'A', '--last-name' => 'B', '--email' => $existing->email])
        ->expectsQuestion('Contraseña (mín. 10 caracteres, mayúsculas, minúsculas y números)', 'ClaveSegura2026')
        ->expectsQuestion('Repita la contraseña', 'ClaveSegura2026')
        ->assertFailed();

    expect(User::role(RoleName::SuperAdmin->value)->count())->toBe(0);
});

it('procesa la cola desde el cron del servidor', function () {
    $commands = collect(app(Schedule::class)->events())->map->command->implode("\n");

    expect($commands)->toContain('queue:work --stop-when-empty');
});

it('incluye el .htaccess para Apache y bloquea archivos ocultos', function () {
    $htaccess = file_get_contents(public_path('.htaccess'));

    expect($htaccess)->toContain('RewriteRule ^ index.php [L]')
        ->and($htaccess)->toContain('<FilesMatch "^\.">');
});
