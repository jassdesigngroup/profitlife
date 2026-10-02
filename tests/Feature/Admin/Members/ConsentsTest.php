<?php

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Consents\Enums\ConsentType;
use App\Domain\Consents\Models\Consent;
use App\Domain\Consents\Models\ConsentTemplate;
use App\Domain\Documents\Models\Document;
use App\Domain\Identity\Enums\RoleName;
use App\Domain\Locations\Models\Location;
use App\Livewire\Admin\Consents\ConsentTemplateForm;
use App\Livewire\Admin\Consents\ConsentTemplateIndex;
use App\Livewire\Admin\Members\MemberConsents;
use App\Livewire\Admin\Members\MemberDocuments;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Storage::fake('local');
    $this->location = Location::factory()->create();
    $this->admin = staffUser(RoleName::Admin, [$this->location]);
    $this->reception = staffUser(RoleName::Reception, [$this->location]);
    $this->member = memberAt($this->location);
    $this->template = ConsentTemplate::factory()->create();
});

describe('plantillas', function () {
    it('publica versiones nuevas y desactiva la anterior', function () {
        Livewire::actingAs($this->admin)->test(ConsentTemplateForm::class, ['type' => 'data_processing'])
            ->assertSet('title', $this->template->title)
            ->set('body', str_repeat('Texto legal actualizado. ', 5))
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.consents.index'));

        $v2 = ConsentTemplate::query()->where('version', 2)->sole();
        expect($v2->is_active)->toBeTrue()
            ->and($this->template->fresh()->is_active)->toBeFalse()
            ->and($this->template->fresh()->body)->not->toBe($v2->body)
            ->and(Activity::query()->where('event', AuditEvent::TemplateVersionCreated->value)->exists())->toBeTrue();
    });

    it('solo quien gestiona plantillas accede a ellas', function () {
        $manager = staffUser(RoleName::LocationManager, [$this->location]);

        $this->actingAs($this->admin)->get(route('admin.consents.index'))->assertOk();
        $this->actingAs($manager)->get(route('admin.consents.index'))->assertForbidden();
        $this->actingAs($this->reception)->get(route('admin.consents.create'))->assertForbidden();
        Livewire::actingAs($manager)->test(ConsentTemplateIndex::class)->assertForbidden();
    });

    it('mantiene una sola versión activa por tipo', function () {
        $v2 = ConsentTemplate::factory()->create(['version' => 2, 'is_active' => false]);

        Livewire::actingAs($this->admin)->test(ConsentTemplateIndex::class)->call('toggle', $v2->id);

        expect($v2->fresh()->is_active)->toBeTrue()
            ->and($this->template->fresh()->is_active)->toBeFalse();
    });
});

describe('aceptación', function () {
    it('registra un consentimiento digital con IP y quién lo capturó', function () {
        Livewire::actingAs($this->reception)->test(MemberConsents::class, ['memberId' => $this->member->id])
            ->call('capture', $this->template->id)
            ->assertSet('signedName', $this->member->full_name)
            ->call('save')
            ->assertHasErrors('accepted')
            ->set('accepted', true)
            ->call('save')
            ->assertHasNoErrors();

        $consent = Consent::query()->sole();
        expect($consent->method->value)->toBe('digital')
            ->and($consent->ip_address)->toBe('127.0.0.1')
            ->and($consent->captured_by)->toBe($this->reception->id)
            ->and($consent->accepted_at)->not->toBeNull()
            ->and(Activity::query()->where('event', AuditEvent::ConsentAccepted->value)->exists())->toBeTrue();
    });

    it('en papel exige el escaneo y lo guarda como documento del consentimiento', function () {
        $component = Livewire::actingAs($this->reception)->test(MemberConsents::class, ['memberId' => $this->member->id])
            ->call('capture', $this->template->id)
            ->set('method', 'paper')
            ->call('save')
            ->assertHasErrors('scan');

        $component->set('scan', UploadedFile::fake()->create('firmado.pdf', 50, 'application/pdf'))->call('save')->assertHasNoErrors();

        $consent = Consent::query()->sole();
        $document = Document::query()->sole();
        expect($consent->document_id)->toBe($document->id)
            ->and($consent->ip_address)->toBeNull()
            ->and($document->documentable_type)->toBe('consent')
            ->and($document->member_id)->toBe($this->member->id);

        // El soporte de un consentimiento no se puede borrar.
        Livewire::actingAs($this->admin)->test(MemberDocuments::class, ['memberId' => $this->member->id])
            ->call('delete', $document->uuid)
            ->assertHasErrors('document');
        expect($document->fresh()->trashed())->toBeFalse();
    });

    it('pide el nombre del acudiente si el cliente es menor', function () {
        $minor = memberAt($this->location, ['birth_date' => now()->subYears(12)->toDateString()]);

        Livewire::actingAs($this->reception)->test(MemberConsents::class, ['memberId' => $minor->id])
            ->call('capture', $this->template->id)
            ->assertSet('signedName', '')
            ->assertSee('Nombre del acudiente que firma');
    });

    it('no acepta dos veces la misma versión ni versiones inactivas', function () {
        $component = Livewire::actingAs($this->reception)->test(MemberConsents::class, ['memberId' => $this->member->id])
            ->call('capture', $this->template->id)->set('accepted', true)->call('save');

        $component->call('capture', $this->template->id)->set('accepted', true)->call('save')->assertHasErrors('templateId');

        $inactive = ConsentTemplate::factory()->create(['type' => ConsentType::ImageUse, 'is_active' => false]);
        $component->call('capture', $inactive->id)->assertNotFound();

        expect(Consent::count())->toBe(1);
    });

    it('marca como desactualizado al publicar una versión nueva y permite revocar', function () {
        $component = Livewire::actingAs($this->reception)->test(MemberConsents::class, ['memberId' => $this->member->id])
            ->call('capture', $this->template->id)->set('accepted', true)->call('save')
            ->assertSee('Aceptado');

        Livewire::actingAs($this->admin)->test(ConsentTemplateForm::class, ['type' => 'data_processing'])
            ->set('body', str_repeat('Nueva versión del texto. ', 5))->call('save');

        Livewire::actingAs($this->reception)->test(MemberConsents::class, ['memberId' => $this->member->id])
            ->assertSee('Versión anterior aceptada');

        $consent = Consent::query()->sole();
        $component->call('revoke', $consent->id);

        expect($consent->fresh()->revoked_at)->not->toBeNull()
            ->and(Activity::query()->where('event', AuditEvent::ConsentRevoked->value)->exists())->toBeTrue();
    });

    it('quien solo ve clientes no registra consentimientos', function () {
        $trainer = staffUser(RoleName::Trainer, [$this->location]);

        Livewire::actingAs($trainer)->test(MemberConsents::class, ['memberId' => $this->member->id])
            ->call('capture', $this->template->id)
            ->assertForbidden();
    });
});
