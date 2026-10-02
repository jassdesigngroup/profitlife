<?php

use App\Domain\Identity\Enums\RoleName;
use App\Domain\Locations\Enums\DayOfWeek;
use App\Domain\Locations\Models\Location;
use App\Domain\Locations\Models\LocationClosure;
use App\Domain\Locations\Models\Room;
use App\Livewire\Admin\Locations\LocationClosures;
use App\Livewire\Admin\Locations\LocationForm;
use App\Livewire\Admin\Locations\LocationHours;
use App\Livewire\Admin\Locations\LocationIndex;
use App\Livewire\Admin\Locations\LocationRooms;
use Livewire\Livewire;

beforeEach(function () {
    $this->location = Location::factory()->create(['name' => 'Sede Centro']);
    $this->admin = staffUser(RoleName::Admin, [$this->location]);
    $this->manager = staffUser(RoleName::LocationManager, [$this->location]);
});

it('crea una sede con slug automático', function () {
    Livewire::actingAs($this->admin)->test(LocationForm::class)
        ->set('name', 'Sede Cañaveral')
        ->assertSet('slug', 'sede-canaveral')
        ->set('code', 'can')
        ->set('address_line', 'Calle 30 # 25-71')
        ->set('city', 'Floridablanca')
        ->set('department', 'Santander')
        ->set('phone', '607 6000000')
        ->set('email', 'CANAVERAL@example.com')
        ->call('save')
        ->assertHasNoErrors();

    $location = Location::query()->where('slug', 'sede-canaveral')->sole();
    expect($location->code)->toBe('CAN')
        ->and($location->email)->toBe('canaveral@example.com')
        ->and($location->timezone)->toBe('America/Bogota')
        ->and($location->is_active)->toBeTrue();
});

it('valida los datos de la sede', function () {
    Livewire::actingAs($this->admin)->test(LocationForm::class)
        ->set('name', '')
        ->set('code', $this->location->code)
        ->set('timezone', 'Marte/Olympus')
        ->call('save')
        ->assertHasErrors(['name' => 'required', 'code' => 'unique', 'timezone', 'address_line' => 'required']);
});

it('un gerente edita su sede pero no puede crear sedes', function () {
    Livewire::actingAs($this->manager)->test(LocationForm::class, ['location' => $this->location])
        ->set('phone', '6071234567')
        ->call('save')
        ->assertHasNoErrors();

    expect($this->location->fresh()->phone)->toBe('6071234567');

    Livewire::actingAs($this->manager)->test(LocationForm::class)->assertForbidden();
    Livewire::actingAs($this->manager)->test(LocationIndex::class)->call('toggleStatus', $this->location->id)->assertForbidden();
});

it('activa y desactiva una sede', function () {
    Livewire::actingAs($this->admin)->test(LocationIndex::class)
        ->call('toggleStatus', $this->location->id)
        ->assertDispatched('toast');

    expect($this->location->fresh()->is_active)->toBeFalse();
});

it('guarda varias franjas por día (jornada partida)', function () {
    Livewire::actingAs($this->manager)->test(LocationHours::class, ['locationId' => $this->location->id])
        ->call('edit')
        ->set('shifts', [
            ['day_of_week' => 1, 'opens_at' => '05:00', 'closes_at' => '12:00'],
            ['day_of_week' => 1, 'opens_at' => '14:00', 'closes_at' => '21:00'],
            ['day_of_week' => 6, 'opens_at' => '07:00', 'closes_at' => '13:00'],
        ])
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('editing', false);

    $hours = $this->location->hours()->get();
    expect($hours)->toHaveCount(3)
        ->and($hours->where('day_of_week', DayOfWeek::Monday))->toHaveCount(2);
});

it('rechaza franjas solapadas o invertidas', function () {
    Livewire::actingAs($this->manager)->test(LocationHours::class, ['locationId' => $this->location->id])
        ->set('shifts', [
            ['day_of_week' => 2, 'opens_at' => '05:00', 'closes_at' => '12:00'],
            ['day_of_week' => 2, 'opens_at' => '11:00', 'closes_at' => '15:00'],
        ])
        ->call('save')
        ->assertHasErrors('shifts.1.opens_at');

    Livewire::actingAs($this->manager)->test(LocationHours::class, ['locationId' => $this->location->id])
        ->set('shifts', [['day_of_week' => 3, 'opens_at' => '18:00', 'closes_at' => '08:00']])
        ->call('save')
        ->assertHasErrors('shifts.0.closes_at');

    expect($this->location->hours()->count())->toBe(0);
});

it('un entrenador ve el horario pero no lo edita', function () {
    $trainer = staffUser(RoleName::Trainer, [$this->location]);

    Livewire::actingAs($trainer)->test(LocationHours::class, ['locationId' => $this->location->id])
        ->assertOk()
        ->call('edit')
        ->assertForbidden();
});

it('registra y elimina cierres de la sede', function () {
    $date = now()->addMonth()->toDateString();

    Livewire::actingAs($this->manager)->test(LocationClosures::class, ['locationId' => $this->location->id])
        ->call('create')
        ->set('closedOn', $date)
        ->set('reason', 'Mantenimiento')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Mantenimiento');

    $closure = LocationClosure::query()->sole();
    expect($closure->location_id)->toBe($this->location->id);

    Livewire::actingAs($this->manager)->test(LocationClosures::class, ['locationId' => $this->location->id])
        ->set('closedOn', $date)
        ->call('save')
        ->assertHasErrors('closedOn');

    Livewire::actingAs($this->manager)->test(LocationClosures::class, ['locationId' => $this->location->id])
        ->call('remove', $closure->id);

    expect(LocationClosure::count())->toBe(0);
});

it('un administrador registra un festivo para todas las sedes', function () {
    $other = Location::factory()->create();

    Livewire::actingAs($this->admin)->test(LocationClosures::class, ['locationId' => $this->location->id])
        ->set('closedOn', now()->addMonth()->toDateString())
        ->set('reason', 'Festivo nacional')
        ->set('allLocations', true)
        ->call('save')
        ->assertHasNoErrors();

    $closure = LocationClosure::query()->sole();
    expect($closure->location_id)->toBeNull()
        ->and(LocationClosure::query()->affecting($other->id)->count())->toBe(1);

    // El gerente lo ve en su sede, pero no puede borrarlo.
    Livewire::actingAs($this->manager)->test(LocationClosures::class, ['locationId' => $this->location->id])
        ->assertSee('Festivo nacional')
        ->call('remove', $closure->id)
        ->assertForbidden();
});

it('no acepta cierres en el pasado', function () {
    Livewire::actingAs($this->manager)->test(LocationClosures::class, ['locationId' => $this->location->id])
        ->set('closedOn', now()->subDay()->toDateString())
        ->call('save')
        ->assertHasErrors('closedOn');
});

it('crea, edita y elimina salas', function () {
    Livewire::actingAs($this->manager)->test(LocationRooms::class, ['locationId' => $this->location->id])
        ->call('create')
        ->set('name', 'Consultorio 3')
        ->set('type', 'consulting_room')
        ->set('capacity', 2)
        ->call('save')
        ->assertHasNoErrors();

    $room = Room::query()->sole();
    expect($room->location_id)->toBe($this->location->id)
        ->and($room->capacity)->toBe(2);

    Livewire::actingAs($this->manager)->test(LocationRooms::class, ['locationId' => $this->location->id])
        ->call('edit', $room->id)
        ->assertSet('name', 'Consultorio 3')
        ->set('isActive', false)
        ->call('save');

    expect($room->fresh()->is_active)->toBeFalse();

    Livewire::actingAs($this->manager)->test(LocationRooms::class, ['locationId' => $this->location->id])
        ->call('delete', $room->id);

    expect($room->fresh()->trashed())->toBeTrue();
});

it('valida las salas', function () {
    Livewire::actingAs($this->manager)->test(LocationRooms::class, ['locationId' => $this->location->id])
        ->set('name', '')
        ->set('type', 'piscina')
        ->set('capacity', 0)
        ->call('save')
        ->assertHasErrors(['name', 'type', 'capacity']);
});

it('recepción ve las salas pero no las gestiona', function () {
    $reception = staffUser(RoleName::Reception, [$this->location]);
    $room = Room::factory()->for($this->location)->create();

    Livewire::actingAs($reception)->test(LocationRooms::class, ['locationId' => $this->location->id])
        ->assertSee($room->name)
        ->call('create')
        ->assertForbidden();
});
