<?php

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Identity\Enums\RoleName;
use App\Domain\Identity\Models\User;
use App\Domain\Locations\Models\Location;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->location = Location::factory()->create();
});

dataset('roles que exigen 2FA', [
    'Super Admin' => RoleName::SuperAdmin,
    'Administrador' => RoleName::Admin,
    'Gerente de sede' => RoleName::LocationManager,
    'Fisioterapeuta' => RoleName::Physiotherapist,
]);

dataset('roles sin 2FA obligatorio', [
    'Recepción' => RoleName::Reception,
    'Entrenador' => RoleName::Trainer,
]);

it('envía a configurar 2FA a quien lo tiene obligatorio', function (RoleName $role) {
    $user = staffUser($role, [$this->location], twoFactor: false);

    expect($user->requiresTwoFactor())->toBeTrue();

    $this->actingAs($user)->get('/admin')->assertRedirect(route('admin.security'));
    $this->actingAs($user)->get('/admin/sedes')->assertRedirect(route('admin.security'));
    $this->actingAs($user)->get('/admin/staff')->assertRedirect(route('admin.security'));
})->with('roles que exigen 2FA');

it('deja entrar al panel cuando el 2FA está confirmado', function (RoleName $role) {
    $user = staffUser($role, [$this->location], twoFactor: true);

    $this->actingAs($user)->get('/admin')->assertOk();
})->with('roles que exigen 2FA');

it('no exige 2FA a los demás roles', function (RoleName $role) {
    $user = staffUser($role, [$this->location], twoFactor: false);

    expect($user->requiresTwoFactor())->toBeFalse();
    $this->actingAs($user)->get('/admin')->assertOk();
})->with('roles sin 2FA obligatorio');

it('no acepta un 2FA habilitado pero sin confirmar', function () {
    $user = staffUser(RoleName::Admin, [$this->location], twoFactor: false);
    $user->forceFill(['two_factor_secret' => encrypt('SECRETO'), 'two_factor_confirmed_at' => null])->save();

    $this->actingAs($user)->get('/admin')->assertRedirect(route('admin.security'));
});

it('permite ver el perfil sin 2FA para poder configurarlo', function () {
    $user = staffUser(RoleName::Admin, [$this->location], twoFactor: false);

    $this->actingAs($user)->get('/admin/perfil')->assertOk()->assertSee('Verificación en dos pasos pendiente');
    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])
        ->get('/admin/perfil/seguridad')->assertOk()->assertSee('Activar verificación');
});

it('pide confirmar la contraseña antes de la página de seguridad', function () {
    $user = staffUser(RoleName::Admin, [$this->location], twoFactor: false);

    $this->actingAs($user)->get('/admin/perfil/seguridad')->assertRedirect(route('password.confirm'));
});

it('completa el flujo de activación y confirmación de 2FA', function () {
    $user = staffUser(RoleName::Physiotherapist, [$this->location], twoFactor: false);
    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);

    $this->from(route('admin.security'))->post('/user/two-factor-authentication')->assertRedirect(route('admin.security'));
    $user->refresh();
    expect($user->two_factor_secret)->not->toBeNull()->and($user->two_factor_confirmed_at)->toBeNull();

    $this->get(route('admin.security'))->assertOk()->assertSee('Escanee el código');

    $code = app(Google2FA::class)->getCurrentOtp(decrypt($user->two_factor_secret));
    $this->from(route('admin.security'))->post('/user/confirmed-two-factor-authentication', ['code' => $code])
        ->assertRedirect(route('admin.security'))
        ->assertSessionHasNoErrors();

    expect($user->fresh()->hasConfirmedTwoFactor())->toBeTrue()
        ->and(Activity::query()->where('event', AuditEvent::TwoFactorEnabled->value)->where('subject_id', $user->id)->exists())->toBeTrue();

    $this->get('/admin')->assertOk();
});

it('pide el código TOTP al iniciar sesión y entra con un código válido', function () {
    $user = staffUser(RoleName::Admin, [$this->location], twoFactor: true);

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/two-factor-challenge');
    $this->assertGuest();

    $this->post('/two-factor-challenge', ['code' => '000000'])->assertSessionHasErrors('code');
    $this->assertGuest();

    $code = app(Google2FA::class)->getCurrentOtp(decrypt($user->two_factor_secret));
    $this->post('/two-factor-challenge', ['code' => $code])->assertRedirect('/admin');
    $this->assertAuthenticatedAs($user);
});

it('acepta un código de recuperación una sola vez', function () {
    $user = staffUser(RoleName::Admin, [$this->location], twoFactor: true);
    $recovery = $user->recoveryCodes()[0];

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    $this->post('/two-factor-challenge', ['recovery_code' => $recovery])->assertRedirect('/admin');

    expect($user->fresh()->recoveryCodes())->not->toContain($recovery);
});

it('impide desactivar el 2FA a quien lo tiene obligatorio', function () {
    $user = staffUser(RoleName::LocationManager, [$this->location], twoFactor: true);

    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])
        ->from(route('admin.security'))
        ->delete('/user/two-factor-authentication')
        ->assertSessionHasErrorsIn('disableTwoFactorAuthentication', 'two_factor');

    expect($user->fresh()->hasConfirmedTwoFactor())->toBeTrue();
});

it('permite desactivar el 2FA a quien no lo tiene obligatorio', function () {
    $user = staffUser(RoleName::Reception, [$this->location], twoFactor: true);

    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])
        ->delete('/user/two-factor-authentication')
        ->assertSessionHasNoErrors();

    expect($user->fresh()->hasConfirmedTwoFactor())->toBeFalse();
});

it('genera secretos TOTP válidos en la factory', function () {
    $user = User::factory()->withTwoFactor()->create();

    expect(app(TwoFactorAuthenticationProvider::class)->verify(
        decrypt($user->two_factor_secret),
        app(Google2FA::class)->getCurrentOtp(decrypt($user->two_factor_secret)),
    ))->toBeTrue();
});
