<?php

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Identity\Enums\RoleName;
use App\Domain\Identity\Notifications\ResetPasswordNotification;
use App\Domain\Locations\Models\Location;
use App\Http\Admin\Responses\GenericPasswordResetLinkResponse;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->user = staffUser(RoleName::Reception, [Location::factory()->create()], twoFactor: false);
});

it('muestra el formulario de recuperación', function () {
    $this->get('/forgot-password')->assertOk()->assertSee('Recuperar acceso');
});

it('envía en cola el enlace de recuperación', function () {
    Notification::fake();

    $this->post('/forgot-password', ['email' => $this->user->email])
        ->assertSessionHas('status', GenericPasswordResetLinkResponse::MESSAGE);

    Notification::assertSentTo($this->user, ResetPasswordNotification::class);
    expect(new ResetPasswordNotification('token'))->toBeInstanceOf(ShouldQueue::class);
});

it('responde igual cuando el correo no existe, sin revelar cuentas', function () {
    Notification::fake();

    $this->post('/forgot-password', ['email' => 'nadie@example.com'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', GenericPasswordResetLinkResponse::MESSAGE);

    Notification::assertNothingSent();
});

it('restablece la contraseña con un token válido y lo audita', function () {
    Notification::fake();

    $this->post('/forgot-password', ['email' => $this->user->email]);

    Notification::assertSentTo($this->user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) {
        $this->get('/reset-password/'.$notification->token.'?email='.urlencode($this->user->email))
            ->assertOk()->assertSee('Nueva contraseña');

        $this->post('/reset-password', [
            'token' => $notification->token,
            'email' => $this->user->email,
            'password' => 'NuevaClave2026',
            'password_confirmation' => 'NuevaClave2026',
        ])->assertSessionHasNoErrors()->assertRedirect(route('login'));

        return true;
    });

    expect(Hash::check('NuevaClave2026', $this->user->fresh()->password))->toBeTrue()
        ->and(Activity::query()->where('event', AuditEvent::PasswordReset->value)->where('subject_id', $this->user->id)->exists())->toBeTrue();
});

it('rechaza un token inválido', function () {
    $this->post('/reset-password', [
        'token' => 'token-falso',
        'email' => $this->user->email,
        'password' => 'NuevaClave2026',
        'password_confirmation' => 'NuevaClave2026',
    ])->assertSessionHasErrors('email');

    expect(Hash::check('password', $this->user->fresh()->password))->toBeTrue();
});

it('exige una contraseña robusta', function () {
    Notification::fake();
    $this->post('/forgot-password', ['email' => $this->user->email]);

    Notification::assertSentTo($this->user, ResetPasswordNotification::class, function ($notification) {
        $this->post('/reset-password', [
            'token' => $notification->token,
            'email' => $this->user->email,
            'password' => 'corta',
            'password_confirmation' => 'corta',
        ])->assertSessionHasErrors('password');

        return true;
    });
});
