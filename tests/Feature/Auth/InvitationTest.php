<?php

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Identity\Enums\RoleName;
use App\Domain\Identity\Models\User;
use App\Domain\Locations\Models\Location;
use App\Domain\Notifications\Enums\NotificationStatus;
use App\Domain\Notifications\Models\NotificationLog;
use App\Domain\Staff\Models\Staff;
use App\Domain\Staff\Notifications\StaffInvitationNotification;
use App\Domain\Staff\Services\InvitationUrl;
use App\Livewire\Admin\Staff\StaffForm;
use App\Livewire\Admin\Staff\StaffIndex;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->location = Location::factory()->create();
    $this->admin = staffUser(RoleName::Admin, [$this->location]);
});

function inviteStaff(User $actor, Location $location, string $email = 'nuevo@example.com'): void
{
    Livewire::actingAs($actor)->test(StaffForm::class)
        ->set('first_name', 'Nueva')
        ->set('last_name', 'Persona')
        ->set('email', $email)
        ->set('roles', [RoleName::Trainer->value])
        ->set('locationIds', [(string) $location->id])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.staff.index'));
}

it('crea el staff sin contraseña y le envía la invitación', function () {
    Notification::fake();

    inviteStaff($this->admin, $this->location);

    $user = User::query()->where('email', 'nuevo@example.com')->sole();
    expect($user->password)->toBeNull()
        ->and($user->hasRole(RoleName::Trainer->value))->toBeTrue()
        ->and(staffOf($user)->locationIds())->toBe([$this->location->id]);

    Notification::assertSentTo($user, StaffInvitationNotification::class);
});

it('envía la invitación en cola', function () {
    Queue::fake();

    inviteStaff($this->admin, $this->location);

    Queue::assertPushed(SendQueuedNotifications::class, fn ($job) => $job->notification instanceof StaffInvitationNotification);
    expect(StaffInvitationNotification::class)->toImplement(ShouldQueue::class);
});

it('registra el envío en notification_logs', function () {
    inviteStaff($this->admin, $this->location);

    $user = User::query()->where('email', 'nuevo@example.com')->sole();
    $log = NotificationLog::query()->sole();

    expect($log->notifiable_type)->toBe('user')
        ->and($log->notifiable_id)->toBe($user->id)
        ->and($log->notification_type)->toBe('staff_invitation_notification')
        ->and($log->channel->value)->toBe('mail')
        ->and($log->recipient)->toBe('nuevo@example.com')
        ->and($log->status)->toBe(NotificationStatus::Sent);
});

it('permite definir la contraseña con el enlace firmado y luego iniciar sesión', function () {
    $user = User::factory()->invited()->role(RoleName::Trainer)->create();
    Staff::factory()->for($user)->atLocations($this->location)->create();
    $url = InvitationUrl::for($user);

    $this->get($url)->assertOk()->assertSee('Active su cuenta')->assertSee($user->email);

    $this->post($url, ['password' => 'ClaveSegura2026', 'password_confirmation' => 'ClaveSegura2026'])
        ->assertRedirect(route('login'))
        ->assertSessionHas('success');

    $user->refresh();
    expect(Hash::check('ClaveSegura2026', $user->password))->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Activity::query()->where('event', AuditEvent::InvitationAccepted->value)->where('subject_id', $user->id)->exists())->toBeTrue();

    $this->post('/login', ['email' => $user->email, 'password' => 'ClaveSegura2026'])->assertRedirect('/admin');
    $this->assertAuthenticatedAs($user);
});

it('no acepta un enlace usado dos veces', function () {
    $user = User::factory()->invited()->create();
    $url = InvitationUrl::for($user);

    $this->post($url, ['password' => 'ClaveSegura2026', 'password_confirmation' => 'ClaveSegura2026'])->assertRedirect();
    $this->post($url, ['password' => 'OtraClave20266', 'password_confirmation' => 'OtraClave20266'])->assertStatus(410);
    $this->get($url)->assertStatus(410);

    expect(Hash::check('ClaveSegura2026', $user->fresh()->password))->toBeTrue();
});

it('rechaza un enlace manipulado', function () {
    $user = User::factory()->invited()->create();
    $other = User::factory()->invited()->create();
    $url = InvitationUrl::for($user);

    $this->get(str_replace("/invitacion/{$user->id}/", "/invitacion/{$other->id}/", $url))->assertForbidden();
    $this->get(preg_replace('/signature=[a-f0-9]+/', 'signature=abc', $url))->assertForbidden();
});

it('rechaza un enlace vencido', function () {
    $user = User::factory()->invited()->create();
    $url = InvitationUrl::for($user);

    $this->travel(config('profitlife.invitations.expires_hours') + 1)->hours();

    $this->get($url)->assertForbidden();
    $this->post($url, ['password' => 'ClaveSegura2026', 'password_confirmation' => 'ClaveSegura2026'])->assertForbidden();
    expect($user->fresh()->password)->toBeNull();
});

it('invalida el enlace si cambia el correo', function () {
    $user = User::factory()->invited()->create();
    $url = InvitationUrl::for($user);

    $user->update(['email' => 'otro@example.com']);

    $this->get($url)->assertStatus(410);
});

it('exige una contraseña robusta al aceptar', function () {
    $user = User::factory()->invited()->create();

    $this->post(InvitationUrl::for($user), ['password' => '123', 'password_confirmation' => '123'])->assertSessionHasErrors('password');

    expect($user->fresh()->password)->toBeNull();
});

it('reenvía la invitación a quien no la ha aceptado', function () {
    Notification::fake();
    $user = User::factory()->invited()->role(RoleName::Trainer)->create();
    $staff = Staff::factory()->for($user)->atLocations($this->location)->create();

    Livewire::actingAs($this->admin)->test(StaffIndex::class)
        ->call('resendInvitation', $staff->id)
        ->assertDispatched('toast');

    Notification::assertSentTo($user, StaffInvitationNotification::class);
    expect(Activity::query()->where('event', AuditEvent::InvitationSent->value)->where('subject_id', $staff->id)->exists())->toBeTrue();
});

it('no reenvía la invitación a quien ya activó su cuenta', function () {
    Notification::fake();
    $trainer = staffUser(RoleName::Trainer, [$this->location], twoFactor: false);

    Livewire::actingAs($this->admin)->test(StaffIndex::class)
        ->call('resendInvitation', staffOf($trainer)->id)
        ->assertForbidden();

    Notification::assertNothingSent();
});

it('añade el perfil de staff a un usuario existente sin perfil (cliente)', function () {
    Notification::fake();
    $member = User::factory()->role(RoleName::Member)->create(['email' => 'cliente@example.com']);

    inviteStaff($this->admin, $this->location, 'cliente@example.com');

    $member->refresh();
    expect($member->hasRole(RoleName::Member->value))->toBeTrue()
        ->and($member->hasRole(RoleName::Trainer->value))->toBeTrue()
        ->and(staffOf($member))->not->toBeNull();

    // Ya tiene contraseña: no necesita invitación.
    Notification::assertNothingSent();
});
