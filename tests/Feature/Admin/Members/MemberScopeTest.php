<?php

use App\Domain\Documents\Actions\StoreDocument;
use App\Domain\Documents\Enums\DocumentCategory;
use App\Domain\Identity\Enums\RoleName;
use App\Domain\Locations\Models\Location;
use App\Domain\Members\Actions\CreateMember;
use App\Domain\Members\DTOs\MemberData;
use App\Domain\Members\Models\EmergencyContact;
use App\Domain\Members\Models\Member;
use App\Domain\Members\Models\MemberNote;
use App\Livewire\Admin\Members\MemberContacts;
use App\Livewire\Admin\Members\MemberDocuments;
use App\Livewire\Admin\Members\MemberForm;
use App\Livewire\Admin\Members\MemberIndex;
use App\Livewire\Admin\Members\MemberNotes;
use App\Livewire\Admin\Members\MemberShow;
use App\Support\Scopes\LocationScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->a = Location::factory()->create();
    $this->b = Location::factory()->create();
    $this->managerA = staffUser(RoleName::LocationManager, [$this->a]);
    $this->memberA = memberAt($this->a, ['first_name' => 'Cliente', 'last_name' => 'Alfa']);
    $this->memberB = memberAt($this->b, ['first_name' => 'Cliente', 'last_name' => 'Beta']);
});

it('lista solo los clientes de sus sedes', function () {
    Livewire::actingAs($this->managerA)->test(MemberIndex::class)
        ->assertSee('Cliente Alfa')
        ->assertDontSee('Cliente Beta')
        ->set('location', (string) $this->b->id)
        ->assertDontSee('Cliente Beta');
});

it('no abre ni edita un cliente ajeno por URL directa', function () {
    $this->actingAs($this->managerA)->get(route('admin.members.show', $this->memberA))->assertOk();
    $this->actingAs($this->managerA)->get(route('admin.members.show', $this->memberB))->assertNotFound();
    $this->actingAs($this->managerA)->get(route('admin.members.edit', $this->memberB))->assertNotFound();
});

it('no accede a la foto de un cliente ajeno', function () {
    $this->memberB->forceFill(['photo_path' => 'members/x/photo.jpg'])->saveQuietly();
    Storage::disk('local')->put('members/x/photo.jpg', 'img');

    $this->actingAs($this->managerA)->get(route('admin.members.photo', $this->memberB))->assertNotFound();
});

it('no opera sobre un cliente ajeno llamando a los componentes', function () {
    Livewire::actingAs($this->managerA)->test(MemberShow::class, ['member' => $this->memberB])->assertForbidden();
    Livewire::actingAs($this->managerA)->test(MemberForm::class, ['member' => $this->memberB])->assertForbidden();

    foreach ([MemberContacts::class, MemberNotes::class, MemberDocuments::class] as $tab) {
        Livewire::actingAs($this->managerA)->test($tab, ['memberId' => $this->memberB->id])->assertNotFound();
    }
});

it('no toca contactos ni notas de otro cliente con un id manipulado', function () {
    $contactB = EmergencyContact::factory()->for($this->memberB)->create();
    $noteB = MemberNote::factory()->for($this->memberB)->create(['author_id' => $this->managerA->id]);

    Livewire::actingAs($this->managerA)->test(MemberContacts::class, ['memberId' => $this->memberA->id])
        ->call('remove', $contactB->id)->assertNotFound();
    Livewire::actingAs($this->managerA)->test(MemberNotes::class, ['memberId' => $this->memberA->id])
        ->call('delete', $noteB->id)->assertNotFound();

    expect($contactB->fresh())->not->toBeNull()->and($noteB->fresh()->trashed())->toBeFalse();
});

it('no crea clientes en una sede ajena', function () {
    Livewire::actingAs($this->managerA)->test(MemberForm::class)
        ->set('home_location_id', (string) $this->b->id)
        ->set('first_name', 'X')->set('last_name', 'Y')
        ->call('save')
        ->assertHasErrors('home_location_id');

    expect(fn () => app(CreateMember::class)->execute(new MemberData($this->b->id, 'X', 'Y'), $this->managerA))
        ->toThrow(AuthorizationException::class);
});

it('no mueve un cliente propio a una sede ajena', function () {
    Livewire::actingAs($this->managerA)->test(MemberForm::class, ['member' => $this->memberA])
        ->set('home_location_id', (string) $this->b->id)
        ->call('save')
        ->assertHasErrors('home_location_id');

    expect($this->memberA->fresh()->home_location_id)->toBe($this->a->id);
});

it('no descarga documentos de un cliente ajeno', function () {
    $document = app(StoreDocument::class)->execute(
        $this->memberB, UploadedFile::fake()->create('contrato.pdf', 10, 'application/pdf'), DocumentCategory::Contract, 'Contrato',
        staffUser(RoleName::Admin, [$this->b]),
    );

    $this->actingAs($this->managerA)->get(route('admin.documents.download', $document))->assertForbidden();
});

it('rechaza en la Policy al cliente ajeno aunque se cargue sin el scope', function () {
    $memberB = Member::query()->withoutGlobalScope(LocationScope::class)->findOrFail($this->memberB->id);
    $gate = Gate::forUser($this->managerA);

    expect($gate->allows('view', $memberB))->toBeFalse()
        ->and($gate->allows('update', $memberB))->toBeFalse()
        ->and($gate->allows('view', $this->memberA))->toBeTrue();
});

it('Administrador ve los clientes de todas las sedes', function () {
    $admin = staffUser(RoleName::Admin, [$this->a]);

    Livewire::actingAs($admin)->test(MemberIndex::class)->assertSee('Cliente Alfa')->assertSee('Cliente Beta');
});
