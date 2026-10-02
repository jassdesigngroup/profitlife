<?php

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Identity\Enums\RoleName;
use App\Domain\Locations\Models\Location;
use App\Livewire\Admin\Members\MemberShow;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->location = Location::factory()->create();
    $this->reception = staffUser(RoleName::Reception, [$this->location]);
    $this->member = memberAt($this->location, ['phone' => '3105559876', 'birth_date' => '1990-07-22']);
});

it('guarda el PIN como hash y nunca el valor', function () {
    Livewire::actingAs($this->reception)->test(MemberShow::class, ['member' => $this->member])
        ->call('openPin')
        ->set('pin', '5821')
        ->set('pin_confirmation', '5821')
        ->call('savePin')
        ->assertHasNoErrors()
        ->assertSet('showPin', false);

    $member = $this->member->fresh();
    expect($member->hasCheckinPin())->toBeTrue()
        ->and($member->checkin_pin_hash)->not->toBe('5821')
        ->and(Hash::check('5821', $member->checkin_pin_hash))->toBeTrue()
        ->and($member->toArray())->not->toHaveKey('checkin_pin_hash');

    $log = Activity::query()->where('event', AuditEvent::PinSet->value)->sole();
    expect($log->causer_id)->toBe($this->reception->id)
        ->and($log->properties->toJson())->not->toContain('5821')
        ->and(Activity::query()->get()->toJson())->not->toContain($member->checkin_pin_hash);
});

it('rechaza PIN débiles o mal formados', function (string $pin) {
    Livewire::actingAs($this->reception)->test(MemberShow::class, ['member' => $this->member])
        ->set('pin', $pin)
        ->set('pin_confirmation', $pin)
        ->call('savePin')
        ->assertHasErrors('pin');

    expect($this->member->fresh()->hasCheckinPin())->toBeFalse();
})->with([
    'tres dígitos' => '123',
    'cinco dígitos' => '58213',
    'letras' => '12ab',
    'repetido' => '7777',
    'ascendente' => '3456',
    'descendente' => '8765',
    'patrón' => '2525',
    'final del teléfono' => '9876',
    'año de nacimiento' => '1990',
    'día y mes' => '2207',
]);

it('exige confirmar el PIN', function () {
    Livewire::actingAs($this->reception)->test(MemberShow::class, ['member' => $this->member])
        ->set('pin', '5821')
        ->set('pin_confirmation', '5822')
        ->call('savePin')
        ->assertHasErrors('pin');
});

it('cambia y elimina el PIN', function () {
    $component = Livewire::actingAs($this->reception)->test(MemberShow::class, ['member' => $this->member])
        ->set('pin', '5821')->set('pin_confirmation', '5821')->call('savePin')
        ->set('pin', '4093')->set('pin_confirmation', '4093')->call('savePin');

    expect(Hash::check('4093', $this->member->fresh()->checkin_pin_hash))->toBeTrue();

    $component->call('clearPin');

    expect($this->member->fresh()->hasCheckinPin())->toBeFalse()
        ->and(Activity::query()->where('event', AuditEvent::PinSet->value)->count())->toBe(2)
        ->and(Activity::query()->where('event', AuditEvent::PinCleared->value)->count())->toBe(1);
});

it('no deja asignar PIN a quien solo puede ver clientes', function () {
    $trainer = staffUser(RoleName::Trainer, [$this->location]);

    Livewire::actingAs($trainer)->test(MemberShow::class, ['member' => $this->member])
        ->call('openPin')->assertForbidden();

    Livewire::actingAs($trainer)->test(MemberShow::class, ['member' => $this->member])
        ->set('pin', '5821')->set('pin_confirmation', '5821')->call('savePin')->assertForbidden();

    expect($this->member->fresh()->hasCheckinPin())->toBeFalse();
});
