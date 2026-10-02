<?php

use App\Domain\Identity\Enums\RoleName;
use App\Domain\Identity\Models\User;
use App\Domain\Locations\Models\Location;
use App\Domain\Staff\Enums\StaffStatus;
use App\Livewire\Admin\Staff\StaffForm;
use App\Livewire\Admin\Staff\StaffIndex;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
    $this->a = Location::factory()->create(['name' => 'Sede Alfa']);
    $this->b = Location::factory()->create(['name' => 'Sede Beta']);
    $this->admin = staffUser(RoleName::Admin, [$this->a, $this->b]);
});

it('busca por nombre, correo y documento', function () {
    $ana = staffUser(RoleName::Trainer, [$this->a], userAttributes: ['email' => 'ana.trainer@example.com']);
    staffOf($ana)->update(['first_name' => 'Ana', 'last_name' => 'Quintero', 'document_number' => '55554444']);
    $luis = staffUser(RoleName::Reception, [$this->a]);
    staffOf($luis)->update(['first_name' => 'Luis', 'last_name' => 'Barrera']);

    Livewire::actingAs($this->admin)->test(StaffIndex::class)
        ->set('search', 'Quintero')->assertSee('Ana Quintero')->assertDontSee('Luis Barrera')
        ->set('search', 'ana.trainer@')->assertSee('Ana Quintero')->assertDontSee('Luis Barrera')
        ->set('search', '5555')->assertSee('Ana Quintero')
        ->set('search', 'Ana Quin')->assertSee('Ana Quintero');
});

it('filtra por rol, sede y estado', function () {
    $trainer = staffUser(RoleName::Trainer, [$this->a]);
    staffOf($trainer)->update(['first_name' => 'Tomás', 'last_name' => 'Entrena']);
    $reception = staffUser(RoleName::Reception, [$this->b]);
    staffOf($reception)->update(['first_name' => 'Rita', 'last_name' => 'Recibe', 'status' => StaffStatus::Inactive]);

    Livewire::actingAs($this->admin)->test(StaffIndex::class)
        ->set('role', 'trainer')->assertSee('Tomás Entrena')->assertDontSee('Rita Recibe')
        ->set('role', '')->set('location', (string) $this->b->id)->assertSee('Rita Recibe')->assertDontSee('Tomás Entrena')
        ->set('location', '')->set('status', 'inactive')->assertSee('Rita Recibe')->assertDontSee('Tomás Entrena');
});

it('ignora filtros con valores no válidos', function () {
    Livewire::actingAs($this->admin)->test(StaffIndex::class)
        ->set('role', "x' OR 1=1")
        ->set('status', 'cualquiera')
        ->assertOk();
});

it('valida el formulario de staff', function () {
    Livewire::actingAs($this->admin)->test(StaffForm::class)
        ->set('first_name', '')
        ->set('email', 'no-es-correo')
        ->set('calendar_color', 'rojo')
        ->set('hired_on', now()->addYear()->toDateString())
        ->call('save')
        ->assertHasErrors(['first_name', 'email', 'calendar_color', 'hired_on', 'roles', 'locationIds']);
});

it('no permite crear staff con un correo ya usado por otro staff', function () {
    $existing = staffUser(RoleName::Trainer, [$this->a]);

    Livewire::actingAs($this->admin)->test(StaffForm::class)
        ->set('first_name', 'Otra')->set('last_name', 'Persona')
        ->set('email', strtoupper($existing->email))
        ->set('roles', ['trainer'])
        ->set('locationIds', [(string) $this->a->id])
        ->call('save')
        ->assertHasErrors('email');
});

it('edita datos, roles y sedes, y mantiene sincronizado el nombre del usuario', function () {
    $trainer = staffUser(RoleName::Trainer, [$this->a]);
    $staff = staffOf($trainer);

    Livewire::actingAs($this->admin)->test(StaffForm::class, ['staff' => $staff])
        ->assertSet('email', $trainer->email)
        ->set('first_name', 'Carolina')
        ->set('last_name', 'Vega')
        ->set('roles', ['physiotherapist'])
        ->set('locationIds', [(string) $this->a->id, (string) $this->b->id])
        ->set('primaryLocationId', (string) $this->b->id)
        ->set('is_bookable', true)
        ->set('professional_license', 'TP-998877')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.staff.index'));

    $staff->refresh();
    $trainer->refresh();
    expect($trainer->name)->toBe('Carolina Vega')
        ->and($trainer->getRoleNames()->all())->toBe(['physiotherapist'])
        ->and($staff->is_bookable)->toBeTrue()
        ->and($staff->locationIds())->toEqualCanonicalizing([$this->a->id, $this->b->id])
        ->and($staff->locations()->wherePivot('is_primary', true)->sole()->id)->toBe($this->b->id);
});

it('desactiva al staff y le corta el acceso', function () {
    $trainer = staffUser(RoleName::Trainer, [$this->a], twoFactor: false);

    Livewire::actingAs($this->admin)->test(StaffIndex::class)
        ->call('toggleStatus', staffOf($trainer)->id)
        ->assertDispatched('toast');

    expect(staffOf($trainer)->status)->toBe(StaffStatus::Inactive)
        ->and($trainer->fresh()->is_active)->toBeFalse();

    auth()->guard('web')->logout();
    $this->post('/login', ['email' => $trainer->email, 'password' => 'password'])->assertSessionHasErrors('email');
    $this->assertGuest();

    Livewire::actingAs($this->admin)->test(StaffIndex::class)->call('toggleStatus', staffOf($trainer)->id);
    expect($trainer->fresh()->is_active)->toBeTrue();
});

it('nadie se desactiva a sí mismo', function () {
    Livewire::actingAs($this->admin)->test(StaffIndex::class)
        ->call('toggleStatus', staffOf($this->admin)->id)
        ->assertForbidden();

    expect($this->admin->fresh()->is_active)->toBeTrue();
});

it('recepción ve el staff pero no lo gestiona', function () {
    $reception = staffUser(RoleName::Reception, [$this->a]);
    $trainer = staffUser(RoleName::Trainer, [$this->a]);

    Livewire::actingAs($reception)->test(StaffIndex::class)
        ->assertSee(staffOf($trainer)->full_name)
        ->assertDontSee('Nuevo staff')
        ->call('toggleStatus', staffOf($trainer)->id)
        ->assertForbidden();

    Livewire::actingAs($reception)->test(StaffForm::class)->assertForbidden();
});

it('un gerente crea staff en su sede con un rol operativo', function () {
    $manager = staffUser(RoleName::LocationManager, [$this->a]);

    Livewire::actingAs($manager)->test(StaffForm::class)
        ->assertSet('locationIds', [(string) $this->a->id])
        ->set('first_name', 'Pedro')->set('last_name', 'Recepción')
        ->set('email', 'pedro@example.com')
        ->set('roles', ['reception'])
        ->call('save')
        ->assertHasNoErrors();

    $user = User::query()->where('email', 'pedro@example.com')->sole();
    expect(staffOf($user)->locationIds())->toBe([$this->a->id]);
});

it('exige la tarjeta profesional al fisioterapeuta', function () {
    Livewire::actingAs($this->admin)->test(StaffForm::class)
        ->set('first_name', 'Fisio')->set('last_name', 'Sin Tarjeta')
        ->set('email', 'fisio.nueva@example.com')
        ->set('roles', ['physiotherapist'])
        ->set('locationIds', [(string) $this->a->id])
        ->call('save')
        ->assertHasErrors(['professional_license' => 'required']);
});
