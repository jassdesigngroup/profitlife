<?php

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Identity\Enums\RoleName;
use App\Domain\Identity\Models\User;
use App\Domain\Locations\Models\Location;
use App\Livewire\Admin\Audit\AuditIndex;
use App\Livewire\Admin\Locations\LocationForm;
use App\Livewire\Admin\Locations\LocationIndex;
use App\Livewire\Admin\Staff\StaffForm;
use App\Livewire\Admin\Staff\StaffIndex;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Notification::fake();
    $this->location = Location::factory()->create(['name' => 'Sede Centro']);
    $this->admin = staffUser(RoleName::Admin, [$this->location]);
});

it('registra creación de usuario y staff, roles, sedes e invitación con usuario e IP', function () {
    Livewire::actingAs($this->admin)->test(StaffForm::class)
        ->set('first_name', 'Lucía')
        ->set('last_name', 'Prada')
        ->set('email', 'lucia@example.com')
        ->set('document_number', '1098765432')
        ->set('document_type', 'CC')
        ->set('roles', [RoleName::Reception->value])
        ->set('locationIds', [(string) $this->location->id])
        ->call('save')
        ->assertHasNoErrors();

    $user = User::query()->where('email', 'lucia@example.com')->sole();

    $events = Activity::query()->where('causer_id', $this->admin->id)->get();
    expect($events->where('log_name', 'users')->where('event', 'created')->where('subject_id', $user->id))->toHaveCount(1)
        ->and($events->where('log_name', 'staff')->where('event', 'created'))->toHaveCount(1)
        ->and($events->where('event', AuditEvent::RolesUpdated->value))->toHaveCount(1)
        ->and($events->where('event', AuditEvent::LocationsAssigned->value))->toHaveCount(1)
        ->and($events->where('event', AuditEvent::InvitationSent->value))->toHaveCount(1);

    $events->each(fn (Activity $a) => expect($a->properties['ip'])->toBe('127.0.0.1')->and($a->created_at)->not->toBeNull());

    // Sin documento ni teléfono en la auditoría.
    $staffCreated = $events->where('log_name', 'staff')->where('event', 'created')->first();
    expect($staffCreated->properties['attributes'])->not->toHaveKey('document_number')
        ->and(json_encode($staffCreated->properties))->not->toContain('1098765432');
});

it('registra la modificación de staff con valores anteriores y nuevos', function () {
    $trainer = staffUser(RoleName::Trainer, [$this->location]);
    $staff = staffOf($trainer);

    Livewire::actingAs($this->admin)->test(StaffForm::class, ['staff' => $staff])
        ->set('job_title', 'Coordinador de entrenamiento')
        ->call('save')
        ->assertHasNoErrors();

    $log = Activity::query()->where('log_name', 'staff')->where('event', 'updated')->where('subject_id', $staff->id)->sole();
    expect($log->causer_id)->toBe($this->admin->id)
        ->and($log->properties['attributes']['job_title'])->toBe('Coordinador de entrenamiento')
        ->and($log->properties['old'])->toHaveKey('job_title')
        ->and($log->description)->toBe('Modificación de staff');
});

it('registra la desactivación del usuario', function () {
    $trainer = staffUser(RoleName::Trainer, [$this->location]);

    Livewire::actingAs($this->admin)->test(StaffIndex::class)->call('toggleStatus', staffOf($trainer)->id);

    $log = Activity::query()->where('log_name', 'users')->where('event', 'updated')->where('subject_id', $trainer->id)->sole();
    expect($log->properties['old']['is_active'])->toBeTrue()
        ->and($log->properties['attributes']['is_active'])->toBeFalse();
});

it('registra la creación y modificación de sedes', function () {
    Livewire::actingAs($this->admin)->test(LocationForm::class)
        ->set('name', 'Sede Norte')
        ->set('code', 'NOR')
        ->set('address_line', 'Calle 1 # 2-3')
        ->set('city', 'Bucaramanga')
        ->set('department', 'Santander')
        ->call('save')
        ->assertHasNoErrors();

    $location = Location::query()->where('code', 'NOR')->sole();

    Livewire::actingAs($this->admin)->test(LocationForm::class, ['location' => $location])
        ->set('name', 'Sede Norte Renovada')
        ->call('save');

    Livewire::actingAs($this->admin)->test(LocationIndex::class)->call('toggleStatus', $location->id);

    $logs = Activity::query()->where('log_name', 'locations')->where('subject_id', $location->id)->orderBy('id')->get();
    expect($logs->pluck('event')->all())->toBe(['created', 'updated', 'updated'])
        ->and($logs[1]->properties['old']['name'])->toBe('Sede Norte')
        ->and($logs[1]->properties['attributes']['name'])->toBe('Sede Norte Renovada')
        ->and($logs[2]->properties['attributes']['is_active'])->toBeFalse()
        ->and($logs->every(fn ($l) => $l->causer_id === $this->admin->id))->toBeTrue();
});

it('muestra la auditoría a quien tiene permiso y filtra por evento', function () {
    $this->post('/login', ['email' => 'nadie@example.com', 'password' => 'x']);

    $this->actingAs($this->admin)->get(route('admin.audit.index'))->assertOk()->assertSee('Auditoría');

    Livewire::actingAs($this->admin)->test(AuditIndex::class)
        ->set('event', AuditEvent::LoginFailed->value)
        ->assertSee('Intento fallido de inicio de sesión')
        ->assertSee('127.0.0.1');
});

it('niega la auditoría a quien no tiene permiso', function () {
    $manager = staffUser(RoleName::LocationManager, [$this->location]);

    $this->actingAs($manager)->get(route('admin.audit.index'))->assertForbidden();
    Livewire::actingAs($manager)->test(AuditIndex::class)->assertForbidden();
});

it('la pantalla de auditoría es de solo lectura', function () {
    $class = new ReflectionClass(AuditIndex::class);

    // Métodos públicos propios (sin los de paginación de Livewire).
    $public = collect($class->getMethods(ReflectionMethod::IS_PUBLIC))
        ->filter(fn (ReflectionMethod $m) => $m->getFileName() === $class->getFileName())
        ->pluck('name')
        ->sort()->values()->all();

    expect($public)->toBe(['mount', 'render', 'toggle', 'updating']);
});
