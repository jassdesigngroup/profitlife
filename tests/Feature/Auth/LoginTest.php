<?php

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Identity\Enums\RoleName;
use App\Domain\Identity\Models\User;
use App\Domain\Locations\Models\Location;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->location = Location::factory()->create();
});

it('muestra la pantalla de inicio de sesión en español', function () {
    $this->get('/login')->assertOk()->assertSee('Ingresar')->assertSee('Correo electrónico');
});

it('inicia sesión con credenciales correctas y registra el acceso', function () {
    $user = staffUser(RoleName::Reception, [$this->location], twoFactor: false);

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/admin');

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->last_login_at)->not->toBeNull()
        ->and($user->fresh()->last_login_ip)->toBe('127.0.0.1');

    $activity = Activity::query()->where('event', AuditEvent::Login->value)->sole();
    expect($activity->causer_id)->toBe($user->id)
        ->and($activity->properties['ip'])->toBe('127.0.0.1');
});

it('acepta el correo sin distinguir mayúsculas', function () {
    $user = staffUser(RoleName::Reception, [$this->location], twoFactor: false, userAttributes: ['email' => 'ana@example.com']);

    $this->post('/login', ['email' => 'ANA@Example.com', 'password' => 'password'])->assertRedirect('/admin');

    $this->assertAuthenticatedAs($user);
});

it('rechaza una contraseña incorrecta con un mensaje genérico y lo audita', function () {
    $user = staffUser(RoleName::Reception, [$this->location], twoFactor: false);

    $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'incorrecta'])
        ->assertRedirect('/login')
        ->assertSessionHasErrors(['email' => 'El correo o la contraseña no son correctos.']);

    $this->assertGuest();

    $failed = Activity::query()->where('event', AuditEvent::LoginFailed->value)->sole();
    expect($failed->subject_id)->toBe($user->id)
        ->and($failed->causer_id)->toBeNull()
        ->and($failed->properties->has('ip'))->toBeTrue()
        // Sin datos personales: ni el email ni la contraseña tecleados.
        ->and(json_encode($failed->properties))->not->toContain($user->email)
        ->and(json_encode($failed->properties))->not->toContain('incorrecta');
});

it('rechaza un correo inexistente con el mismo mensaje', function () {
    $this->post('/login', ['email' => 'nadie@example.com', 'password' => 'password'])
        ->assertSessionHasErrors(['email' => 'El correo o la contraseña no son correctos.']);

    $this->assertGuest();
    expect(Activity::query()->where('event', AuditEvent::LoginFailed->value)->sole()->subject_id)->toBeNull();
});

it('no deja entrar a un usuario desactivado', function () {
    $user = staffUser(RoleName::Reception, [$this->location], twoFactor: false, userAttributes: ['is_active' => false]);

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('no deja entrar a un invitado que no ha definido su contraseña', function () {
    $user = User::factory()->invited()->role(RoleName::Reception)->create();

    $this->post('/login', ['email' => $user->email, 'password' => ''])->assertSessionHasErrors();
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('bloquea el inicio de sesión tras cinco intentos fallidos', function () {
    $user = staffUser(RoleName::Reception, [$this->location], twoFactor: false);

    foreach (range(1, 5) as $attempt) {
        $this->post('/login', ['email' => $user->email, 'password' => 'incorrecta'])->assertSessionHasErrors('email');
    }

    // Incluso con la contraseña correcta queda bloqueado.
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    expect(session('errors')->first('email'))->toStartWith('Demasiados intentos de acceso');

    $this->assertGuest();
    expect(Activity::query()->where('event', AuditEvent::Lockout->value)->count())->toBe(1)
        ->and(Activity::query()->where('event', AuditEvent::LoginFailed->value)->count())->toBe(5);
});

it('cierra la sesión y lo registra en la auditoría', function () {
    $user = staffUser(RoleName::Reception, [$this->location], twoFactor: false);

    $this->actingAs($user)->post('/logout')->assertRedirect('/');

    $this->assertGuest();
    expect(Activity::query()->where('event', AuditEvent::Logout->value)->where('causer_id', $user->id)->exists())->toBeTrue();
});

it('expulsa a un usuario desactivado que tenía la sesión abierta', function () {
    $user = staffUser(RoleName::Reception, [$this->location], twoFactor: false);
    $this->actingAs($user);

    $user->update(['is_active' => false]);

    $this->get('/admin')->assertRedirect(route('login'));
    $this->assertGuest();
});

it('redirige a los invitados al login', function () {
    $this->get('/admin')->assertRedirect(route('login'));
    $this->get('/admin/sedes')->assertRedirect(route('login'));
});
