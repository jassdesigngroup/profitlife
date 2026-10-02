<?php

use App\Domain\Identity\Enums\RoleName;
use App\Domain\Locations\Models\Location;
use App\Domain\Locations\Models\LocationClosure;
use App\Domain\Locations\Models\Room;
use App\Domain\Staff\Actions\SyncStaffLocations;
use App\Domain\Staff\Models\Staff;
use App\Livewire\Admin\Layout\LocationSwitcher;
use App\Livewire\Admin\Locations\LocationClosures;
use App\Livewire\Admin\Locations\LocationForm;
use App\Livewire\Admin\Locations\LocationHours;
use App\Livewire\Admin\Locations\LocationIndex;
use App\Livewire\Admin\Locations\LocationRooms;
use App\Livewire\Admin\Locations\LocationShow;
use App\Livewire\Admin\Staff\StaffForm;
use App\Livewire\Admin\Staff\StaffIndex;
use App\Support\Locations\CurrentLocation;
use App\Support\Scopes\LocationScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function () {
    $this->a = Location::factory()->create(['name' => 'Sede Alfa']);
    $this->b = Location::factory()->create(['name' => 'Sede Beta']);

    $this->roomA = Room::factory()->for($this->a)->create(['name' => 'Sala Alfa']);
    $this->roomB = Room::factory()->for($this->b)->create(['name' => 'Sala Beta']);

    $this->managerA = staffUser(RoleName::LocationManager, [$this->a]);
    $this->trainerA = staffUser(RoleName::Trainer, [$this->a]);
    $this->trainerB = staffUser(RoleName::Trainer, [$this->b]);
    $this->staffB = staffOf($this->trainerB);
});

describe('sedes', function () {
    it('lista solo las sedes del gerente', function () {
        Livewire::actingAs($this->managerA)->test(LocationIndex::class)
            ->assertSee('Sede Alfa')
            ->assertDontSee('Sede Beta');
    });

    it('no abre la sede ajena por URL directa', function () {
        $this->actingAs($this->managerA)->get(route('admin.locations.show', $this->a))->assertOk();
        $this->actingAs($this->managerA)->get(route('admin.locations.show', $this->b))->assertNotFound();
        $this->actingAs($this->managerA)->get(route('admin.locations.edit', $this->b))->assertNotFound();
    });

    it('no edita la sede ajena llamando al componente', function () {
        // Aunque se le entregue el modelo directamente (sin el scope), la Policy lo rechaza.
        Livewire::actingAs($this->managerA)->test(LocationForm::class, ['location' => $this->b])->assertForbidden();
        Livewire::actingAs($this->managerA)->test(LocationShow::class, ['location' => $this->b])->assertForbidden();
    });

    it('no activa ni desactiva la sede ajena con un id manipulado', function () {
        Livewire::actingAs($this->managerA)->test(LocationIndex::class)
            ->call('toggleStatus', $this->b->id)
            ->assertNotFound();

        expect($this->b->fresh()->is_active)->toBeTrue();
    });

    it('no gestiona horarios ni cierres de la sede ajena', function () {
        Livewire::actingAs($this->managerA)->test(LocationHours::class, ['locationId' => $this->b->id])->assertNotFound();
        Livewire::actingAs($this->managerA)->test(LocationClosures::class, ['locationId' => $this->b->id])->assertNotFound();
    });

    it('no puede manipular el id bloqueado del componente para saltar a otra sede', function () {
        expect(fn () => Livewire::actingAs($this->managerA)->test(LocationHours::class, ['locationId' => $this->a->id])
            ->set('locationId', $this->b->id))
            ->toThrow(CannotUpdateLockedPropertyException::class);
    });

    it('no borra un cierre de la sede ajena', function () {
        $closureB = LocationClosure::factory()->for($this->b)->create();

        Livewire::actingAs($this->managerA)->test(LocationClosures::class, ['locationId' => $this->a->id])
            ->call('remove', $closureB->id)
            ->assertNotFound();

        expect($closureB->fresh())->not->toBeNull();
    });

    it('no crea cierres generales para todas las sedes', function () {
        Livewire::actingAs($this->managerA)->test(LocationClosures::class, ['locationId' => $this->a->id])
            ->set('closedOn', now()->addWeek()->toDateString())
            ->set('allLocations', true)
            ->call('save')
            ->assertForbidden();

        expect(LocationClosure::query()->whereNull('location_id')->exists())->toBeFalse();
    });

    it('rechaza en la Policy la sede ajena aunque se cargue sin el scope', function () {
        $b = Location::query()->withoutGlobalScope(LocationScope::class)->findOrFail($this->b->id);

        $gate = Gate::forUser($this->managerA);
        expect($gate->allows('view', $b))->toBeFalse()
            ->and($gate->allows('update', $b))->toBeFalse()
            ->and($gate->allows('manageHours', $b))->toBeFalse()
            ->and($gate->allows('view', $this->a))->toBeTrue()
            ->and($gate->allows('update', $this->a))->toBeTrue();
    });
});

describe('salas', function () {
    it('no muestra las salas de la sede ajena', function () {
        $this->actingAs($this->managerA);

        expect(Room::query()->pluck('name')->all())->toBe(['Sala Alfa']);
        Livewire::actingAs($this->managerA)->test(LocationRooms::class, ['locationId' => $this->b->id])->assertNotFound();
    });

    it('no edita ni borra una sala ajena con un id manipulado', function () {
        Livewire::actingAs($this->managerA)->test(LocationRooms::class, ['locationId' => $this->a->id])
            ->call('edit', $this->roomB->id)
            ->assertNotFound();

        Livewire::actingAs($this->managerA)->test(LocationRooms::class, ['locationId' => $this->a->id])
            ->call('delete', $this->roomB->id)
            ->assertNotFound();

        expect($this->roomB->fresh()->trashed())->toBeFalse();
    });

    it('rechaza en la Policy la sala ajena aunque se cargue sin el scope', function () {
        $roomB = Room::query()->withoutGlobalScope(LocationScope::class)->findOrFail($this->roomB->id);
        $gate = Gate::forUser($this->managerA);

        expect($gate->allows('view', $roomB))->toBeFalse()
            ->and($gate->allows('update', $roomB))->toBeFalse()
            ->and($gate->allows('delete', $roomB))->toBeFalse()
            ->and($gate->allows('create', [Room::class, $this->b]))->toBeFalse()
            ->and($gate->allows('update', $this->roomA))->toBeTrue();
    });
});

describe('staff', function () {
    it('lista solo el staff de sus sedes', function () {
        Livewire::actingAs($this->managerA)->test(StaffIndex::class)
            ->assertSee(staffOf($this->trainerA)->full_name)
            ->assertDontSee($this->staffB->full_name);
    });

    it('no ve el staff ajeno ni filtrando por la sede ajena', function () {
        Livewire::actingAs($this->managerA)->test(StaffIndex::class)
            ->set('location', (string) $this->b->id)
            ->assertDontSee($this->staffB->full_name);
    });

    it('no abre la edición del staff ajeno por URL directa', function () {
        $this->actingAs($this->managerA)->get(route('admin.staff.edit', staffOf($this->trainerA)))->assertOk();
        $this->actingAs($this->managerA)->get(route('admin.staff.edit', $this->staffB))->assertNotFound();
    });

    it('no desactiva ni reenvía invitación al staff ajeno con un id manipulado', function () {
        Livewire::actingAs($this->managerA)->test(StaffIndex::class)
            ->call('toggleStatus', $this->staffB->id)
            ->assertNotFound();

        Livewire::actingAs($this->managerA)->test(StaffIndex::class)
            ->call('resendInvitation', $this->staffB->id)
            ->assertNotFound();

        expect($this->staffB->fresh()->isActive())->toBeTrue()
            ->and($this->trainerB->fresh()->is_active)->toBeTrue();
    });

    it('no asigna la sede ajena a su staff', function () {
        Livewire::actingAs($this->managerA)->test(StaffForm::class, ['staff' => staffOf($this->trainerA)])
            ->set('locationIds', [(string) $this->a->id, (string) $this->b->id])
            ->call('save')
            ->assertHasErrors('locationIds.1');

        expect(staffOf($this->trainerA)->locationIds())->toBe([$this->a->id]);
    });

    it('no asigna la sede ajena aunque se salte el formulario', function () {
        expect(fn () => app(SyncStaffLocations::class)->execute(staffOf($this->trainerA), [$this->a->id, $this->b->id], null, $this->managerA))
            ->toThrow(AuthorizationException::class);
    });

    it('conserva las sedes ajenas de un empleado compartido al editarlo', function () {
        $shared = staffUser(RoleName::Trainer, [$this->a, $this->b]);

        app(SyncStaffLocations::class)->execute(staffOf($shared), [$this->a->id], $this->a->id, $this->managerA);

        expect(staffOf($shared)->locationIds())->toEqualCanonicalizing([$this->a->id, $this->b->id]);
    });

    it('rechaza en la Policy al staff ajeno aunque se cargue sin el scope', function () {
        $staffB = Staff::query()->withoutGlobalScope(LocationScope::class)->findOrFail($this->staffB->id);
        $gate = Gate::forUser($this->managerA);

        expect($gate->allows('view', $staffB))->toBeFalse()
            ->and($gate->allows('update', $staffB))->toBeFalse()
            ->and($gate->allows('toggleStatus', $staffB))->toBeFalse();
    });
});

describe('selector de sede', function () {
    it('no deja seleccionar una sede ajena', function () {
        Livewire::actingAs($this->managerA)->test(LocationSwitcher::class)
            ->set('locationId', (string) $this->b->id)
            ->assertForbidden();
    });

    it('ignora una sede ajena guardada en la sesión', function () {
        $this->actingAs($this->managerA);
        session([CurrentLocation::SESSION_KEY => $this->b->id]);

        expect(app(CurrentLocation::class)->id())->toBeNull();
    });

    it('filtra los listados sin conceder acceso', function () {
        $admin = staffUser(RoleName::Admin, [$this->a]);

        $this->actingAs($admin);
        app(CurrentLocation::class)->set($this->b->id);

        Livewire::actingAs($admin)->test(LocationIndex::class)
            ->assertSee('Sede Beta')
            ->assertDontSee('Sede Alfa');
    });
});

describe('Super Admin y Administrador', function () {
    it('ven todas las sedes, salas y staff', function (RoleName $role) {
        $user = staffUser($role, [$this->a]);

        Livewire::actingAs($user)->test(LocationIndex::class)->assertSee('Sede Alfa')->assertSee('Sede Beta');
        Livewire::actingAs($user)->test(StaffIndex::class)
            ->assertSee(staffOf($this->trainerA)->full_name)
            ->assertSee($this->staffB->full_name);
        $this->actingAs($user)->get(route('admin.locations.show', $this->b))->assertOk();
    })->with([RoleName::SuperAdmin, RoleName::Admin]);
});
