<?php

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Enums\RoleName;
use App\Domain\Identity\Models\User;
use App\Domain\Locations\Models\Location;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->location = Location::factory()->create();
});

/**
 * Pantalla => roles que deben poder abrirla. El resto recibe 403.
 */
dataset('pantallas', function () {
    $all = [RoleName::SuperAdmin, RoleName::Admin, RoleName::LocationManager, RoleName::Reception, RoleName::Physiotherapist, RoleName::Trainer];

    return [
        'inicio' => ['admin.dashboard', [], $all],
        'listado de sedes' => ['admin.locations.index', [], $all],
        'ficha de sede' => ['admin.locations.show', ['location'], $all],
        'crear sede' => ['admin.locations.create', [], [RoleName::SuperAdmin, RoleName::Admin]],
        'editar sede' => ['admin.locations.edit', ['location'], [RoleName::SuperAdmin, RoleName::Admin, RoleName::LocationManager]],
        'listado de staff' => ['admin.staff.index', [], [RoleName::SuperAdmin, RoleName::Admin, RoleName::LocationManager, RoleName::Reception]],
        'crear staff' => ['admin.staff.create', [], [RoleName::SuperAdmin, RoleName::Admin, RoleName::LocationManager]],
        'roles' => ['admin.roles.index', [], [RoleName::SuperAdmin, RoleName::Admin]],
        'auditoría' => ['admin.audit.index', [], [RoleName::SuperAdmin, RoleName::Admin]],
        'listado de clientes' => ['admin.members.index', [], $all],
        'ficha de cliente' => ['admin.members.show', ['member'], $all],
        'crear cliente' => ['admin.members.create', [], [RoleName::SuperAdmin, RoleName::Admin, RoleName::LocationManager, RoleName::Reception]],
        'editar cliente' => ['admin.members.edit', ['member'], [RoleName::SuperAdmin, RoleName::Admin, RoleName::LocationManager, RoleName::Reception]],
        'asistencia' => ['admin.check-ins.index', [], $all],
        'agenda' => ['admin.appointments.index', [], $all],
        'servicios' => ['admin.services.index', [], [RoleName::SuperAdmin, RoleName::Admin]],
        'crear servicio' => ['admin.services.create', [], [RoleName::SuperAdmin, RoleName::Admin]],
        'disponibilidad del staff' => ['admin.staff.availability', ['staff'], [RoleName::SuperAdmin, RoleName::Admin, RoleName::LocationManager]],
        'membresías' => ['admin.memberships.index', [], [RoleName::SuperAdmin, RoleName::Admin, RoleName::LocationManager, RoleName::Reception]],
        'pagos' => ['admin.payments.index', [], [RoleName::SuperAdmin, RoleName::Admin, RoleName::LocationManager, RoleName::Reception]],
        'planes' => ['admin.plans.index', [], [RoleName::SuperAdmin, RoleName::Admin]],
        'crear plan' => ['admin.plans.create', [], [RoleName::SuperAdmin, RoleName::Admin]],
        'ajustes' => ['admin.settings', [], [RoleName::SuperAdmin, RoleName::Admin]],
        'plantillas de consentimiento' => ['admin.consents.index', [], [RoleName::SuperAdmin, RoleName::Admin]],
    ];
});

it('aplica la matriz de acceso por rol', function (string $route, array $params, array $allowed) {
    $parameters = in_array('location', $params, true) ? ['location' => $this->location->id] : [];
    if (in_array('member', $params, true)) {
        $parameters = ['member' => memberAt($this->location)->id];
    }
    if (in_array('staff', $params, true)) {
        $parameters = ['staff' => staffOf(staffUser(RoleName::Physiotherapist, [$this->location]))->id];
    }

    foreach (RoleName::staffRoles() as $role) {
        $user = staffUser($role, [$this->location]);
        $expected = in_array($role, $allowed, true) ? 200 : 403;

        $this->actingAs($user)->get(route($route, $parameters))
            ->assertStatus($expected);
    }
})->with('pantallas');

it('niega el panel a un cliente con 403', function () {
    $member = User::factory()->role(RoleName::Member)->create();

    $this->actingAs($member)->get('/admin')->assertForbidden();
    $this->actingAs($member)->get('/admin/sedes')->assertForbidden();
    $this->actingAs($member)->get('/admin/perfil')->assertForbidden();
});

it('niega el panel a un usuario sin roles', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin')->assertForbidden();
});

it('niega una pantalla cuando se retira el permiso al rol', function () {
    $user = staffUser(RoleName::Reception, [$this->location], twoFactor: false);
    $this->actingAs($user)->get('/admin/staff')->assertOk();

    Role::findByName(RoleName::Reception->value)->revokePermissionTo(Permission::StaffView->value);

    $this->actingAs($user->fresh())->get('/admin/staff')->assertForbidden();
});

it('crea todos los permisos del catálogo, incluidos los de módulos futuros', function () {
    expect(PermissionModel::query()->pluck('name')->sort()->values()->all())
        ->toBe(collect(Permission::values())->sort()->values()->all());

    foreach (['members.view', 'memberships.create', 'check-ins.create', 'appointments.view', 'clinical-notes.sign', 'training.view', 'assessments.create', 'payments.refund', 'reports.export'] as $name) {
        expect(PermissionModel::query()->where('name', $name)->exists())->toBeTrue();
    }

    foreach (Permission::cases() as $permission) {
        expect($permission->value)->toMatch('/^[a-z-]+\.[a-z-]+$/');
    }
});

it('crea los siete roles', function () {
    expect(Role::query()->pluck('name')->sort()->values()->all())
        ->toBe(collect(RoleName::values())->sort()->values()->all());
});

it('no da ningún permiso clínico a Recepción ni a Gerente de sede', function (RoleName $role) {
    $permissions = Role::findByName($role->value)->permissions->pluck('name');

    foreach (Permission::clinical() as $clinical) {
        expect($permissions)->not->toContain($clinical->value);
    }
})->with([RoleName::Reception, RoleName::LocationManager]);

it('da los permisos clínicos al Fisioterapeuta', function () {
    $permissions = Role::findByName(RoleName::Physiotherapist->value)->permissions->pluck('name');

    expect($permissions)->toContain('clinical-notes.view', 'clinical-notes.create', 'clinical-notes.sign');
});

it('da todos los permisos a Super Admin y solo a él el cambio de permisos', function () {
    expect(Role::findByName(RoleName::SuperAdmin->value)->permissions)->toHaveCount(count(Permission::cases()));

    foreach (RoleName::cases() as $role) {
        if ($role !== RoleName::SuperAdmin) {
            expect(Role::findByName($role->value)->hasPermissionTo(Permission::UsersManagePermissions->value))->toBeFalse();
        }
    }
});

it('limita el acceso a todas las sedes a Super Admin y Administrador', function () {
    foreach (RoleName::cases() as $role) {
        $expected = in_array($role, [RoleName::SuperAdmin, RoleName::Admin], true);

        expect(Role::findByName($role->value)->hasPermissionTo(Permission::LocationsViewAll->value))->toBe($expected);
    }
});

it('el seeder es idempotente y respeta los cambios hechos desde el panel', function () {
    $reception = Role::findByName(RoleName::Reception->value);
    $reception->revokePermissionTo(Permission::PaymentsCreate->value);
    $before = PermissionModel::count();

    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(PermissionModel::count())->toBe($before)
        ->and(Role::count())->toBe(count(RoleName::cases()))
        ->and($reception->fresh()->hasPermissionTo(Permission::PaymentsCreate->value))->toBeFalse()
        ->and(Role::findByName(RoleName::SuperAdmin->value)->permissions)->toHaveCount(count(Permission::cases()));
});

it('muestra en la barra lateral solo lo permitido', function () {
    $trainer = staffUser(RoleName::Trainer, [$this->location]);

    $this->actingAs($trainer)->get('/admin')
        ->assertOk()
        ->assertSee('Sedes')
        ->assertDontSee('Roles y permisos')
        ->assertDontSee('Auditoría')
        ->assertDontSee(route('admin.staff.index'));

    $admin = staffUser(RoleName::Admin, [$this->location]);

    $this->actingAs($admin)->get('/admin')
        ->assertSee('Roles y permisos')
        ->assertSee('Auditoría')
        ->assertSee(route('admin.staff.index'));
});
