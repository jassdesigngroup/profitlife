<?php

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Enums\RoleName;
use App\Domain\Locations\Enums\DayOfWeek;

it('usa el formato recurso.accion y no repite permisos', function () {
    $values = Permission::values();

    expect($values)->toHaveCount(count(array_unique($values)));

    foreach (Permission::cases() as $permission) {
        expect($permission->value)->toMatch('/^[a-z-]+\.[a-z-]+$/')
            ->and($permission->label())->not->toBeEmpty();
    }
});

it('agrupa los permisos por recurso con etiqueta en español', function () {
    $groups = Permission::grouped();

    expect(array_keys($groups))->toContain('locations', 'staff', 'members', 'clinical-notes', 'payments', 'reports')
        ->and(Permission::resourceLabel('clinical-notes'))->toBe('Notas clínicas');
});

it('marca como clínicos solo los permisos de notas clínicas', function () {
    expect(array_map(fn ($p) => $p->value, Permission::clinical()))
        ->each->toStartWith('clinical-notes.');
});

it('exige 2FA solo a los roles indicados', function () {
    $required = array_values(array_filter(RoleName::cases(), fn ($r) => $r->requiresTwoFactor()));

    expect($required)->toBe([RoleName::SuperAdmin, RoleName::Admin, RoleName::LocationManager, RoleName::Physiotherapist]);
});

it('tiene etiquetas en español para roles y días', function () {
    expect(RoleName::LocationManager->label())->toBe('Gerente de sede')
        ->and(RoleName::Member->label())->toBe('Cliente')
        ->and(DayOfWeek::from(1)->label())->toBe('Lunes')
        ->and(DayOfWeek::from(7)->label())->toBe('Domingo');
});

it('no ofrece el rol Cliente en el módulo de staff', function () {
    expect(RoleName::staffRoles())->not->toContain(RoleName::Member);
});
