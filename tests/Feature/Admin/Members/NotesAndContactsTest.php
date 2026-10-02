<?php

use App\Domain\Identity\Enums\RoleName;
use App\Domain\Locations\Models\Location;
use App\Domain\Members\Models\EmergencyContact;
use App\Domain\Members\Models\MemberNote;
use App\Livewire\Admin\Members\MemberContacts;
use App\Livewire\Admin\Members\MemberNotes;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->location = Location::factory()->create();
    $this->reception = staffUser(RoleName::Reception, [$this->location]);
    $this->member = memberAt($this->location);
});

it('añade notas sin guardar su texto en la auditoría', function () {
    Livewire::actingAs($this->reception)->test(MemberNotes::class, ['memberId' => $this->member->id])
        ->set('body', 'Pidió cambiar de horario por trabajo')
        ->set('pinned', true)
        ->call('add')
        ->assertHasNoErrors()
        ->assertSee('Pidió cambiar de horario por trabajo');

    $note = MemberNote::query()->sole();
    expect($note->author_id)->toBe($this->reception->id)->and($note->is_pinned)->toBeTrue()
        ->and(Activity::query()->get()->toJson())->not->toContain('cambiar de horario');
});

it('solo el autor edita su nota y borrar notas ajenas exige members.delete', function () {
    $note = MemberNote::factory()->for($this->member)->create(['author_id' => $this->reception->id, 'body' => 'Original']);
    $other = staffUser(RoleName::Reception, [$this->location]);

    Livewire::actingAs($other)->test(MemberNotes::class, ['memberId' => $this->member->id])
        ->call('edit', $note->id)->assertForbidden();
    Livewire::actingAs($other)->test(MemberNotes::class, ['memberId' => $this->member->id])
        ->call('delete', $note->id)->assertForbidden();

    Livewire::actingAs($this->reception)->test(MemberNotes::class, ['memberId' => $this->member->id])
        ->call('edit', $note->id)
        ->set('editingBody', 'Corregida')
        ->call('saveEdit')
        ->assertHasNoErrors();
    expect($note->fresh()->body)->toBe('Corregida');

    $admin = staffUser(RoleName::Admin, [$this->location]);
    Livewire::actingAs($admin)->test(MemberNotes::class, ['memberId' => $this->member->id])->call('delete', $note->id);
    expect($note->fresh()->trashed())->toBeTrue();
});

it('gestiona contactos de emergencia con un único principal', function () {
    $component = Livewire::actingAs($this->reception)->test(MemberContacts::class, ['memberId' => $this->member->id]);

    $component->call('create')->set('name', 'Marta Ríos')->set('phone', '311 222 3344')->call('save')->assertHasNoErrors();
    $first = EmergencyContact::query()->sole();
    expect($first->is_primary)->toBeTrue()->and($first->phone)->toBe('3112223344');

    $component->call('create')->set('name', 'Jorge Ríos')->set('phone', '3009998877')->set('is_primary', true)->call('save');

    expect($first->fresh()->is_primary)->toBeFalse()
        ->and(EmergencyContact::query()->where('is_primary', true)->sole()->name)->toBe('Jorge Ríos');

    $component->call('remove', EmergencyContact::query()->where('name', 'Jorge Ríos')->sole()->id);
    expect($first->fresh()->is_primary)->toBeTrue();
});

it('valida los contactos', function () {
    Livewire::actingAs($this->reception)->test(MemberContacts::class, ['memberId' => $this->member->id])
        ->call('create')->set('name', '')->set('phone', 'xx')->set('email', 'mal')->call('save')
        ->assertHasErrors(['name', 'phone', 'email']);
});

it('quien solo ve clientes no edita notas ni contactos', function () {
    $trainer = staffUser(RoleName::Trainer, [$this->location]);

    Livewire::actingAs($trainer)->test(MemberNotes::class, ['memberId' => $this->member->id])
        ->set('body', 'x')->call('add')->assertForbidden();
    Livewire::actingAs($trainer)->test(MemberContacts::class, ['memberId' => $this->member->id])
        ->call('create')->assertForbidden();
});
