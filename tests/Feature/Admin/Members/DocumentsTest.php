<?php

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Documents\Enums\DocumentSensitivity;
use App\Domain\Documents\Models\Document;
use App\Domain\Identity\Enums\RoleName;
use App\Domain\Locations\Models\Location;
use App\Livewire\Admin\Members\MemberDocuments;
use App\Livewire\Admin\Members\MemberShow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Storage::fake('local');
    $this->location = Location::factory()->create();
    $this->reception = staffUser(RoleName::Reception, [$this->location]);
    $this->physio = staffUser(RoleName::Physiotherapist, [$this->location]);
    $this->member = memberAt($this->location);
});

function upload($user, $member, string $category, ?UploadedFile $file = null)
{
    return Livewire::actingAs($user)->test(MemberDocuments::class, ['memberId' => $member->id])
        ->call('create')
        ->set('title', 'Soporte')
        ->set('category', $category)
        ->set('file', $file ?? UploadedFile::fake()->create('soporte.pdf', 120, 'application/pdf'))
        ->call('save');
}

it('guarda un documento administrativo de forma privada', function () {
    upload($this->reception, $this->member, 'identification')->assertHasNoErrors();

    $document = Document::query()->sole();
    expect($document->sensitivity)->toBe(DocumentSensitivity::Administrative)
        ->and($document->member_id)->toBe($this->member->id)
        ->and($document->documentable_type)->toBe('member')
        ->and($document->uuid)->toHaveLength(36)
        ->and($document->checksum)->toHaveLength(64)
        ->and($document->path)->toStartWith("members/{$this->member->id}/documents/")
        ->and($document->path)->not->toContain('soporte')
        ->and($document->uploaded_by)->toBe($this->reception->id);

    Storage::disk('local')->assertExists($document->path);
    expect(Activity::query()->where('event', AuditEvent::DocumentUploaded->value)->exists())->toBeTrue();
});

it('descarga con autorización y registra la descarga', function () {
    upload($this->reception, $this->member, 'contract');
    $document = Document::query()->sole();

    $this->actingAs($this->reception)->get(route('admin.documents.download', $document))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertDownload('soporte.pdf');

    expect(Activity::query()->where('event', AuditEvent::DocumentDownloaded->value)->where('causer_id', $this->reception->id)->exists())->toBeTrue();
});

it('rechaza tipos de archivo no permitidos', function () {
    upload($this->reception, $this->member, 'other', UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload'))
        ->assertHasErrors('file');

    expect(Document::count())->toBe(0);
});

it('Recepción no puede subir documentos clínicos ni verlos', function () {
    upload($this->reception, $this->member, 'medical')->assertHasErrors('category');

    upload($this->physio, $this->member, 'medical')->assertHasNoErrors();
    $clinical = Document::query()->sole();
    expect($clinical->sensitivity)->toBe(DocumentSensitivity::Clinical);

    Livewire::actingAs($this->reception)->test(MemberDocuments::class, ['memberId' => $this->member->id])
        ->assertDontSee('soporte.pdf');
    $this->actingAs($this->reception)->get(route('admin.documents.download', $clinical))->assertForbidden();

    Livewire::actingAs($this->physio)->test(MemberDocuments::class, ['memberId' => $this->member->id])
        ->assertSee('soporte.pdf');
    $this->actingAs($this->physio)->get(route('admin.documents.download', $clinical))->assertOk();
});

it('el fisioterapeuta solo sube documentos clínicos y puede borrar los suyos', function () {
    Livewire::actingAs($this->physio)->test(MemberDocuments::class, ['memberId' => $this->member->id])
        ->call('create')
        ->assertSee('Médico')
        ->assertDontSee('Contrato');

    upload($this->physio, $this->member, 'contract')->assertHasErrors('category');
    upload($this->physio, $this->member, 'medical')->assertHasNoErrors();

    Livewire::actingAs($this->physio)->test(MemberDocuments::class, ['memberId' => $this->member->id])
        ->call('delete', Document::query()->sole()->uuid)->assertHasNoErrors();

    expect(Document::withTrashed()->sole()->trashed())->toBeTrue();
});

it('un entrenador no sube documentos', function () {
    $trainer = staffUser(RoleName::Trainer, [$this->location]);

    Livewire::actingAs($trainer)->test(MemberDocuments::class, ['memberId' => $this->member->id])
        ->assertDontSee('wire:click="create"', false)
        ->call('create')
        ->assertForbidden();
});

it('Gerente de sede tampoco ve documentos clínicos', function () {
    upload($this->physio, $this->member, 'medical');
    $manager = staffUser(RoleName::LocationManager, [$this->location]);

    $this->actingAs($manager)->get(route('admin.documents.download', Document::query()->sole()))->assertForbidden();
});

it('borra solo quien lo subió o quien tiene members.delete', function () {
    upload($this->reception, $this->member, 'contract');
    $document = Document::query()->sole();
    $otherReception = staffUser(RoleName::Reception, [$this->location]);

    Livewire::actingAs($otherReception)->test(MemberDocuments::class, ['memberId' => $this->member->id])
        ->call('delete', $document->uuid)->assertForbidden();

    Livewire::actingAs($this->reception)->test(MemberDocuments::class, ['memberId' => $this->member->id])
        ->call('delete', $document->uuid)->assertHasNoErrors();

    expect($document->fresh()->trashed())->toBeTrue();
    Storage::disk('local')->assertExists($document->path);
});

it('sube y sirve la foto del cliente de forma privada', function () {
    Livewire::actingAs($this->reception)->test(MemberShow::class, ['member' => $this->member])
        ->set('photo', UploadedFile::fake()->image('foto.jpg', 200, 200))
        ->assertHasNoErrors();

    $path = $this->member->fresh()->photo_path;
    expect($path)->toStartWith("members/{$this->member->id}/photo-");
    Storage::disk('local')->assertExists($path);

    $this->actingAs($this->reception)->get(route('admin.members.photo', $this->member))->assertOk();
    auth()->guard('web')->logout();
    $this->get(route('admin.members.photo', $this->member))->assertRedirect(route('login'));
});

it('rechaza una foto que no es imagen', function () {
    Livewire::actingAs($this->reception)->test(MemberShow::class, ['member' => $this->member])
        ->set('photo', UploadedFile::fake()->create('foto.pdf', 10, 'application/pdf'))
        ->assertHasErrors('photo');
});
