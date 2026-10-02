<?php

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Enums\RoleName;
use App\Domain\Locations\Models\Location;
use App\Domain\Staff\Actions\SyncStaffRoles;
use App\Livewire\Admin\Roles\RoleEdit;
use App\Livewire\Admin\Staff\StaffForm;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->location = Location::factory()->create();
    $this->superAdmin = staffUser(RoleName::SuperAdmin, [$this->location]);
    $this->admin = staffUser(RoleName::Admin, [$this->location]);
    $this->manager = staffUser(RoleName::LocationManager, [$this->location]);
    $this->trainer = staffUser(RoleName::Trainer, [$this->location]);
});

describe('rol Super Admin', function () {
    it('solo Super Admin puede asignarlo', function () {
        Livewire::actingAs($this->superAdmin)->test(StaffForm::class, ['staff' => staffOf($this->trainer)])
            ->set('roles', [RoleName::SuperAdmin->value])
            ->call('save')
            ->assertHasNoErrors();

        expect($this->trainer->fresh()->hasRole(RoleName::SuperAdmin->value))->toBeTrue()
            ->and(Activity::query()->where('event', AuditEvent::RolesUpdated->value)->where('subject_id', $this->trainer->id)->exists())->toBeTrue();
    });

    it('un Administrador no puede asignarlo desde el formulario', function () {
        Livewire::actingAs($this->admin)->test(StaffForm::class, ['staff' => staffOf($this->trainer)])
            ->set('roles', [RoleName::SuperAdmin->value])
            ->call('save')
            ->assertHasErrors('roles.0');

        expect($this->trainer->fresh()->hasRole(RoleName::SuperAdmin->value))->toBeFalse();
    });

    it('un Administrador no puede asignarlo saltándose el formulario', function () {
        expect(fn () => app(SyncStaffRoles::class)->execute(staffOf($this->trainer), [RoleName::SuperAdmin], $this->admin))
            ->toThrow(AuthorizationException::class);

        expect($this->trainer->fresh()->hasRole(RoleName::SuperAdmin->value))->toBeFalse();
    });

    it('no aparece como opción para un Administrador', function () {
        Livewire::actingAs($this->admin)->test(StaffForm::class)
            ->assertDontSee('value="super_admin"', false)
            ->assertSee('value="admin"', false);
    });

    it('un Administrador no puede editar ni desactivar a un Super Admin', function () {
        $this->actingAs($this->admin)->get(route('admin.staff.edit', staffOf($this->superAdmin)))->assertForbidden();

        expect($this->admin->can('toggleStatus', staffOf($this->superAdmin)))->toBeFalse()
            ->and($this->admin->can('manageRoles', staffOf($this->superAdmin)))->toBeFalse();
    });

    it('un Administrador no puede quitarle el rol a un Super Admin', function () {
        expect(fn () => app(SyncStaffRoles::class)->execute(staffOf($this->superAdmin), [RoleName::Trainer], $this->admin))
            ->toThrow(AuthorizationException::class);

        expect($this->superAdmin->fresh()->isSuperAdmin())->toBeTrue();
    });
});

describe('jerarquía de roles', function () {
    it('un Gerente solo asigna roles operativos', function () {
        expect(RoleName::assignableBy($this->manager))->toBe([RoleName::Reception, RoleName::Physiotherapist, RoleName::Trainer]);

        Livewire::actingAs($this->manager)->test(StaffForm::class, ['staff' => staffOf($this->trainer)])
            ->set('roles', [RoleName::Admin->value])
            ->call('save')
            ->assertHasErrors('roles.0');

        Livewire::actingAs($this->manager)->test(StaffForm::class, ['staff' => staffOf($this->trainer)])
            ->set('roles', [RoleName::Reception->value, RoleName::Trainer->value])
            ->call('save')
            ->assertHasNoErrors();

        expect($this->trainer->fresh()->getRoleNames()->sort()->values()->all())->toBe(['reception', 'trainer']);
    });

    it('un Gerente no puede editar a otro Gerente ni a un Administrador de su sede', function () {
        $otherManager = staffUser(RoleName::LocationManager, [$this->location]);

        $this->actingAs($this->manager)->get(route('admin.staff.edit', staffOf($otherManager)))->assertForbidden();
        $this->actingAs($this->manager)->get(route('admin.staff.edit', staffOf($this->admin)))->assertForbidden();
    });

    it('nadie cambia sus propios roles', function () {
        expect($this->admin->can('manageRoles', staffOf($this->admin)))->toBeFalse()
            ->and(fn () => app(SyncStaffRoles::class)->execute(staffOf($this->admin), [RoleName::Admin, RoleName::Trainer], $this->admin))
            ->toThrow(AuthorizationException::class);
    });

    it('conserva el rol de Cliente al cambiar roles de staff', function () {
        $this->trainer->assignRole(RoleName::Member->value);

        app(SyncStaffRoles::class)->execute(staffOf($this->trainer), [RoleName::Reception], $this->admin);

        expect($this->trainer->fresh()->getRoleNames()->sort()->values()->all())->toBe(['member', 'reception']);
    });
});

describe('cambio de permisos', function () {
    it('solo Super Admin cambia los permisos de un rol y queda auditado', function () {
        $role = Role::findByName(RoleName::Reception->value);
        $permissions = $role->permissions->pluck('name')->reject(fn ($p) => $p === Permission::PaymentsCreate->value)->values()->all();

        Livewire::actingAs($this->superAdmin)->test(RoleEdit::class, ['role' => $role])
            ->set('permissions', [...$permissions, Permission::ReportsView->value])
            ->call('save')
            ->assertHasNoErrors();

        $role->refresh();
        expect($role->hasPermissionTo(Permission::PaymentsCreate->value))->toBeFalse()
            ->and($role->hasPermissionTo(Permission::ReportsView->value))->toBeTrue();

        $log = Activity::query()->where('event', AuditEvent::PermissionsUpdated->value)->sole();
        expect($log->causer_id)->toBe($this->superAdmin->id)
            ->and($log->properties['added'])->toBe([Permission::ReportsView->value])
            ->and($log->properties['removed'])->toBe([Permission::PaymentsCreate->value]);
    });

    it('un Administrador puede ver la matriz pero no cambiarla', function () {
        $role = Role::findByName(RoleName::Reception->value);
        $before = $role->permissions->pluck('name')->sort()->values()->all();

        $this->actingAs($this->admin)->get(route('admin.roles.edit', $role))->assertOk()->assertSee('Vista de solo lectura');

        Livewire::actingAs($this->admin)->test(RoleEdit::class, ['role' => $role])
            ->set('permissions', [Permission::AuditView->value])
            ->call('save')
            ->assertForbidden();

        expect($role->fresh()->permissions->pluck('name')->sort()->values()->all())->toBe($before);
    });

    it('nadie edita los permisos del rol Super Admin', function () {
        Livewire::actingAs($this->superAdmin)->test(RoleEdit::class, ['role' => Role::findByName(RoleName::SuperAdmin->value)])
            ->set('permissions', [])
            ->call('save')
            ->assertForbidden();

        expect(Role::findByName(RoleName::SuperAdmin->value)->permissions)->toHaveCount(count(Permission::cases()));
    });

    it('ignora permisos inventados', function () {
        Livewire::actingAs($this->superAdmin)->test(RoleEdit::class, ['role' => Role::findByName(RoleName::Trainer->value)])
            ->set('permissions', ['root.everything'])
            ->call('save')
            ->assertHasErrors('permissions.0');
    });
});
