<?php

use App\Domain\Identity\Enums\RoleName;
use App\Domain\Identity\Models\User;
use App\Domain\Locations\Models\Location;
use App\Domain\Members\Enums\MemberStatus;
use App\Domain\Members\Models\Member;
use App\Domain\Settings\Services\Settings;
use App\Livewire\Admin\Members\MemberForm;
use App\Livewire\Admin\Members\MemberIndex;
use App\Livewire\Admin\Members\MemberShow;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->location = Location::factory()->create(['name' => 'Sede Centro', 'city' => 'Bucaramanga', 'department' => 'Santander']);
    $this->reception = staffUser(RoleName::Reception, [$this->location]);
});

function fillMember($component, Location $location, array $overrides = [])
{
    $values = array_merge([
        'home_location_id' => (string) $location->id,
        'first_name' => 'Valeria',
        'last_name' => 'Ríos',
        'document_type' => 'CC',
        'document_number' => '1098123456',
        'birth_date' => '1995-03-10',
        'phone' => '+57 (310) 555-1234',
        'email' => 'Valeria.Rios@Example.com',
    ], $overrides);

    foreach ($values as $key => $value) {
        $component->set($key, $value);
    }

    return $component;
}

it('registra un cliente con número visible configurable y datos normalizados', function () {
    app(Settings::class)->set('members', 'number_prefix', 'PL');

    fillMember(Livewire::actingAs($this->reception)->test(MemberForm::class), $this->location)
        ->call('save')
        ->assertHasNoErrors();

    $member = Member::query()->sole();
    expect($member->member_number)->toBe('PL-'.str_pad((string) $member->id, 6, '0', STR_PAD_LEFT))
        ->and($member->phone)->toBe('+573105551234')
        ->and($member->email)->toBe('valeria.rios@example.com')
        ->and($member->status)->toBe(MemberStatus::Active)
        ->and($member->joined_on->toDateString())->toBe(now()->toDateString())
        ->and($member->created_by)->toBe($this->reception->id)
        ->and($member->user_id)->toBeNull();
});

it('usa el prefijo de config si no hay ajuste', function () {
    config(['profitlife.members.number_prefix' => 'XY']);

    fillMember(Livewire::actingAs($this->reception)->test(MemberForm::class), $this->location)->call('save');

    expect(Member::query()->sole()->member_number)->toStartWith('XY-');
});

it('propone la sede del usuario y su ciudad', function () {
    Livewire::actingAs($this->reception)->test(MemberForm::class)
        ->assertSet('home_location_id', (string) $this->location->id)
        ->assertSet('city', 'Bucaramanga');
});

it('valida el formulario y no repite documentos', function () {
    memberAt($this->location, ['document_type' => 'CC', 'document_number' => '1098123456']);

    fillMember(Livewire::actingAs($this->reception)->test(MemberForm::class), $this->location)
        ->call('save')
        ->assertHasErrors('document_number');

    fillMember(Livewire::actingAs($this->reception)->test(MemberForm::class), $this->location, [
        'first_name' => '', 'email' => 'no-es-correo', 'phone' => 'abc', 'birth_date' => now()->addDay()->toDateString(),
        'document_type' => '', 'document_number' => '123',
    ])->call('save')->assertHasErrors(['first_name', 'email', 'phone', 'birth_date', 'document_type']);
});

it('avisa cuando el cliente es menor de edad', function () {
    Livewire::actingAs($this->reception)->test(MemberForm::class)
        ->set('birth_date', now()->subYears(15)->toDateString())
        ->assertSee('Cliente menor de edad (15 años)')
        ->assertSee('Ley 1581 de 2012')
        ->set('birth_date', now()->subYears(30)->toDateString())
        ->assertDontSee('Cliente menor de edad');
});

it('enlaza al cliente con el usuario si es del staff', function () {
    $trainer = staffUser(RoleName::Trainer, [$this->location], userAttributes: ['email' => 'coach@example.com']);

    fillMember(Livewire::actingAs($this->reception)->test(MemberForm::class), $this->location, ['email' => 'COACH@example.com'])
        ->call('save')
        ->assertHasNoErrors();

    $member = Member::query()->sole();
    expect($member->user_id)->toBe($trainer->id)
        ->and($trainer->fresh()->hasRole(RoleName::Member->value))->toBeTrue()
        ->and($trainer->fresh()->hasRole(RoleName::Trainer->value))->toBeTrue();

    $this->actingAs($this->reception)->get(route('admin.members.show', $member))->assertSee('También es staff');
});

it('no enlaza a un usuario que no es staff', function () {
    User::factory()->create(['email' => 'otro@example.com']);

    fillMember(Livewire::actingAs($this->reception)->test(MemberForm::class), $this->location, ['email' => 'otro@example.com'])->call('save');

    expect(Member::query()->sole()->user_id)->toBeNull();
});

it('edita los datos del cliente', function () {
    $member = memberAt($this->location);

    Livewire::actingAs($this->reception)->test(MemberForm::class, ['member' => $member])
        ->assertSet('first_name', $member->first_name)
        ->set('first_name', 'Nuevo')
        ->set('joined_on', '2024-01-15')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.members.show', $member));

    expect($member->fresh()->first_name)->toBe('Nuevo')
        ->and($member->fresh()->joined_on->toDateString())->toBe('2024-01-15');
});

it('busca por nombre, documento, teléfono y número de cliente', function () {
    $ana = memberAt($this->location, ['first_name' => 'Ana', 'last_name' => 'Pardo', 'document_number' => '55443322', 'phone' => '3001112233']);
    memberAt($this->location, ['first_name' => 'Luis', 'last_name' => 'Torres']);

    $component = Livewire::actingAs($this->reception)->test(MemberIndex::class);

    foreach (['Pardo', 'Ana Par', '5544', '300 111', $ana->member_number] as $term) {
        $component->set('search', $term)->assertSee('Ana Pardo')->assertDontSee('Luis Torres');
    }
});

it('filtra por estado', function () {
    memberAt($this->location, ['first_name' => 'Activa', 'last_name' => 'Uno']);
    memberAt($this->location, ['first_name' => 'Bloqueada', 'last_name' => 'Dos', 'status' => MemberStatus::Blocked]);

    Livewire::actingAs($this->reception)->test(MemberIndex::class)
        ->set('status', 'blocked')
        ->assertSee('Bloqueada Dos')
        ->assertDontSee('Activa Uno');
});

it('cambia el estado del cliente y lo audita', function () {
    $member = memberAt($this->location);

    Livewire::actingAs($this->reception)->test(MemberShow::class, ['member' => $member])
        ->call('openStatus')
        ->set('newStatus', 'blocked')
        ->call('saveStatus')
        ->assertHasNoErrors();

    expect($member->fresh()->status)->toBe(MemberStatus::Blocked);

    $log = Activity::query()->where('log_name', 'members')->where('event', 'updated')->where('subject_id', $member->id)->sole();
    expect($log->properties['attributes']['status'])->toBe('blocked');
});

it('solo quien tiene members.delete elimina clientes (borrado lógico)', function () {
    $member = memberAt($this->location);

    Livewire::actingAs($this->reception)->test(MemberShow::class, ['member' => $member])->call('delete')->assertForbidden();
    expect($member->fresh()->trashed())->toBeFalse();

    $admin = staffUser(RoleName::Admin, [$this->location]);
    Livewire::actingAs($admin)->test(MemberShow::class, ['member' => $member])->call('delete')->assertRedirect(route('admin.members.index'));

    expect(Member::withTrashed()->withoutGlobalScopes()->find($member->id)->trashed())->toBeTrue();
});

it('no guarda datos personales sensibles en la auditoría', function () {
    fillMember(Livewire::actingAs($this->reception)->test(MemberForm::class), $this->location)->call('save');

    $json = Activity::query()->where('log_name', 'members')->get()->toJson();

    expect($json)->not->toContain('1098123456')
        ->and($json)->not->toContain('5551234')
        ->and($json)->not->toContain('1995-03-10');
});

it('muestra el número de clientes activos en el inicio', function () {
    memberAt($this->location);
    memberAt($this->location, ['status' => MemberStatus::Inactive]);

    $this->actingAs($this->reception)->get('/admin')->assertOk()->assertSee('Clientes activos');
});
