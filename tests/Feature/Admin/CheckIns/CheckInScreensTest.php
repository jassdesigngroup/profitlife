<?php

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\CheckIns\Actions\IssueAccessCode;
use App\Domain\CheckIns\Enums\CheckInResult;
use App\Domain\CheckIns\Models\CheckIn;
use App\Domain\CheckIns\Models\KioskDevice;
use App\Domain\CheckIns\Models\MemberAccessCredential;
use App\Domain\CheckIns\Notifications\MemberAccessCodeNotification;
use App\Domain\Identity\Enums\RoleName;
use App\Domain\Locations\Models\Location;
use App\Domain\Settings\Services\Settings;
use App\Livewire\Admin\CheckIns\CheckInIndex;
use App\Livewire\Admin\Locations\LocationKiosks;
use App\Livewire\Admin\Members\MemberCheckIns;
use App\Livewire\Admin\Settings\SettingsPage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-05 08:00', 'America/Bogota'));
    $this->location = Location::factory()->create(['name' => 'Cabecera']);
    $this->other = Location::factory()->create(['name' => 'Provenza']);
    $this->manager = staffUser(RoleName::LocationManager, [$this->location]);
    $this->reception = staffUser(RoleName::Reception, [$this->location]);
    $this->member = memberAt($this->location, ['first_name' => 'Laura', 'last_name' => 'Quintero']);
    paidSale($this->member, $this->location, $this->manager);
});

describe('asistencia', function () {
    it('recepción busca y registra un ingreso', function () {
        Livewire::actingAs($this->reception)->test(CheckInIndex::class)
            ->set('search', 'Quintero')
            ->assertSee('Laura Quintero')
            ->call('checkIn', $this->member->id)
            ->assertSet('outcome.accepted', true)
            ->assertSee('Ingreso registrado: Laura Quintero')
            ->assertSet('search', '');

        expect(CheckIn::query()->sole()->registered_by)->toBe($this->reception->id);
    });

    it('muestra el rechazo y solo ofrece autorizar a quien puede', function () {
        $nobody = memberAt($this->location, ['first_name' => 'Ana', 'last_name' => 'Ruiz']);

        Livewire::actingAs($this->reception)->test(CheckInIndex::class)
            ->call('checkIn', $nobody->id)
            ->assertSet('outcome.accepted', false)
            ->assertSet('outcome.canOverride', false)
            ->assertSee('Sin membresía vigente');

        Livewire::actingAs($this->manager)->test(CheckInIndex::class)
            ->call('checkIn', $nobody->id)
            ->assertSet('outcome.canOverride', true)
            ->assertSee('Autorizar ingreso');
    });

    it('no registra en una sede ajena ni a clientes que no ve', function () {
        $foreign = memberAt($this->other);

        Livewire::actingAs($this->reception)->test(CheckInIndex::class)
            ->set('deskLocationId', (string) $this->other->id)
            ->call('checkIn', $this->member->id)
            ->assertHasErrors('deskLocationId');

        Livewire::actingAs($this->reception)->test(CheckInIndex::class)
            ->call('checkIn', $foreign->id)
            ->assertNotFound();

        expect(CheckIn::query()->count())->toBe(0);
    });

    it('el entrenador ve la asistencia pero no registra', function () {
        $trainer = staffUser(RoleName::Trainer, [$this->location]);

        Livewire::actingAs($trainer)->test(CheckInIndex::class)
            ->assertOk()
            ->assertDontSee('Registrar ingreso')
            ->call('checkIn', $this->member->id)
            ->assertHasErrors('deskLocationId');

        expect(CheckIn::query()->count())->toBe(0);
    });

    it('lista solo los ingresos de las sedes del usuario, con totales', function () {
        registerCheckIn($this->member, $this->location);
        $foreign = memberAt($this->other, ['first_name' => 'Zoe', 'last_name' => 'Ajena']);
        registerCheckIn($foreign, $this->other);

        Livewire::actingAs($this->reception)->test(CheckInIndex::class)
            ->assertSee('Laura Quintero')
            ->assertDontSee('Zoe Ajena')
            ->assertViewHas('accepted', 1)
            ->assertViewHas('rejected', 0);
    });
});

describe('ficha del cliente', function () {
    it('genera, anula y envía el código QR', function () {
        Notification::fake();

        $component = Livewire::actingAs($this->reception)->test(MemberCheckIns::class, ['memberId' => $this->member->id])
            ->assertSee('Sin código de acceso')
            ->call('issueCode')
            ->assertSee('Imprimir o descargar');

        expect(MemberAccessCredential::query()->where('is_active', true)->count())->toBe(1);

        $component->call('sendCode')->assertHasNoErrors();
        Notification::assertSentTo($this->member, MemberAccessCodeNotification::class);
        expect(Activity::query()->where('event', AuditEvent::AccessCodeSent->value)->exists())->toBeTrue();

        $component->call('revokeCode')->assertSee('Sin código de acceso');
        expect(MemberAccessCredential::query()->where('is_active', true)->count())->toBe(0);
    });

    it('registra el ingreso desde la ficha', function () {
        Livewire::actingAs($this->reception)->test(MemberCheckIns::class, ['memberId' => $this->member->id])
            ->call('register')
            ->assertSet('outcome.accepted', true);

        expect(CheckIn::query()->sole()->result)->toBe(CheckInResult::Accepted);
    });

    it('quien no edita al cliente no ve ni gestiona su código', function () {
        app(IssueAccessCode::class)->execute($this->member, $this->manager);
        $trainer = staffUser(RoleName::Trainer, [$this->location]);

        Livewire::actingAs($trainer)->test(MemberCheckIns::class, ['memberId' => $this->member->id])
            ->assertDontSee('Imprimir o descargar')
            ->call('issueCode')->assertForbidden();

        $this->actingAs($trainer)->get(route('admin.members.access-card', $this->member))->assertForbidden();
        $this->actingAs($this->reception)->get(route('admin.members.access-card', $this->member))->assertOk()->assertSee('<svg', false);
    });

    it('la pestaña exige el permiso de ver asistencia', function () {
        Role::findByName(RoleName::Reception->value)->revokePermissionTo('check-ins.view');

        Livewire::actingAs($this->reception->fresh())->test(MemberCheckIns::class, ['memberId' => $this->member->id])
            ->assertForbidden();
    });
});

describe('código de acceso por correo', function () {
    it('la página firmada muestra el código y deja de servir al anularlo', function () {
        $credential = app(IssueAccessCode::class)->execute($this->member, $this->manager);
        $url = URL::temporarySignedRoute('access-code.show', now()->addDay(), ['credential' => $credential->id]);

        $this->get($url)->assertOk()->assertSee('Hola, Laura')->assertSee('<svg', false)->assertDontSee('Quintero');
        $this->get(route('access-code.show', ['credential' => $credential->id]))->assertForbidden();

        app(IssueAccessCode::class)->execute($this->member, $this->manager);
        $this->get($url)->assertOk()->assertSee('Código no disponible');
    });
});

describe('kioscos de la sede', function () {
    it('el gerente crea y vincula un kiosco; el enlace lleva el token', function () {
        $component = Livewire::actingAs($this->manager)->test(LocationKiosks::class, ['locationId' => $this->location->id])
            ->call('create')
            ->set('name', 'Tablet entrada')
            ->call('save')
            ->assertHasNoErrors();

        $device = KioskDevice::query()->sole();
        expect($device->location_id)->toBe($this->location->id);

        $component->call('pair', $device->id)
            ->assertSet('showPair', true)
            ->assertSee('/kiosco#vincular=', false);

        expect($device->tokens()->count())->toBe(1)
            ->and(Activity::query()->where('event', AuditEvent::KioskPaired->value)->exists())->toBeTrue();

        $component->call('closePair')->assertSet('pairingUrl', '');
        $component->call('unpair', $device->id);
        expect($device->tokens()->count())->toBe(0);
    });

    it('recepción no gestiona kioscos ni se accede a los de otra sede', function () {
        $device = KioskDevice::factory()->create(['location_id' => $this->location->id]);
        $foreign = KioskDevice::factory()->create(['location_id' => $this->other->id]);

        Livewire::actingAs($this->reception)->test(LocationKiosks::class, ['locationId' => $this->location->id])
            ->call('pair', $device->id)->assertForbidden();

        Livewire::actingAs($this->manager)->test(LocationKiosks::class, ['locationId' => $this->location->id])
            ->call('pair', $foreign->id)->assertNotFound();

        Livewire::actingAs($this->manager)->test(LocationKiosks::class, ['locationId' => $this->other->id])
            ->assertNotFound();
    });
});

it('los ajustes guardan la ventana de repetidos', function () {
    $admin = staffUser(RoleName::Admin, [$this->location]);

    Livewire::actingAs($admin)->test(SettingsPage::class)
        ->set('duplicateMinutes', 45)
        ->call('save')
        ->assertHasNoErrors();

    expect(app(Settings::class)->checkInDuplicateMinutes())->toBe(45);
});
