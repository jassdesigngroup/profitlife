<?php

use App\Domain\Consents\Enums\ConsentType;
use App\Domain\Consents\Models\Consent;
use App\Domain\Consents\Models\ConsentTemplate;
use App\Domain\Documents\Models\Document;
use App\Domain\Identity\Enums\RoleName;
use App\Domain\Identity\Models\User;
use App\Domain\Locations\Models\Location;
use App\Domain\Members\Actions\DeleteMember;
use App\Domain\Members\Models\Member;
use App\Domain\Physiotherapy\Actions\AddClinicalAddendum;
use App\Domain\Physiotherapy\Actions\GrantEmergencyAccess;
use App\Domain\Physiotherapy\Actions\OpenPhysiotherapyRecord;
use App\Domain\Physiotherapy\Actions\RecordPhysiotherapySession;
use App\Domain\Physiotherapy\Actions\SignClinicalNote;
use App\Domain\Physiotherapy\Enums\ClinicalAction;
use App\Domain\Physiotherapy\Enums\NoteType;
use App\Domain\Physiotherapy\Enums\SessionType;
use App\Domain\Physiotherapy\Models\ClinicalAccessLog;
use App\Domain\Physiotherapy\Models\ClinicalNote;
use App\Domain\Physiotherapy\Models\PhysiotherapyRecord;
use App\Domain\Physiotherapy\Notifications\EmergencyAccessNotification;
use App\Domain\Physiotherapy\Services\ClinicalTeam;
use App\Livewire\Admin\Appointments\AppointmentPanel;
use App\Livewire\Admin\Clinical\ClinicalRecordShow;
use App\Livewire\Admin\Clinical\ClinicalTeam as ClinicalTeamComponent;
use App\Livewire\Admin\Members\MemberPhysiotherapy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-10-05 07:00', 'America/Bogota'));
    $this->location = Location::factory()->create();
    $this->service = serviceAt([$this->location], ['name' => 'Fisioterapia', 'is_clinical' => true]);
    $this->physio = professional($this->location, [$this->service], '08:00', '18:00');
    $this->otherPhysio = professional($this->location, [$this->service], '08:00', '18:00');
    $this->reception = staffUser(RoleName::Reception, [$this->location]);
    $this->manager = staffUser(RoleName::LocationManager, [$this->location]);
    $this->admin = staffUser(RoleName::Admin, [$this->location]);
    $this->superAdmin = staffUser(RoleName::SuperAdmin, [$this->location]);
    $this->member = memberAt($this->location, ['first_name' => 'Laura', 'last_name' => 'Quintero']);
});

function openRecordFor(Member $member, User $physio, array $data = []): PhysiotherapyRecord
{
    return app(OpenPhysiotherapyRecord::class)->execute($member, $physio, $data + [
        'reason_for_consultation' => 'Dolor lumbar', 'medical_history' => 'Hernia L5', 'medications' => null, 'allergies' => 'Penicilina',
    ]);
}

function clinicalConsent(Member $member, User $actor): void
{
    $template = ConsentTemplate::factory()->create(['type' => ConsentType::ClinicalTreatment]);
    Consent::query()->create([
        'member_id' => $member->id, 'consent_template_id' => $template->id, 'method' => 'paper',
        'signed_name' => $member->full_name, 'accepted_at' => now(), 'captured_by' => $actor->id,
    ]);
}

function sessionNote(PhysiotherapyRecord $record, User $physio, SessionType $type = SessionType::Treatment): ClinicalNote
{
    return app(RecordPhysiotherapySession::class)->execute($record, $physio, [
        'session_type' => $type, 'performed_at' => CarbonImmutable::now()->subHour(), 'pain_scale' => 6,
        'treatment_plan_id' => null, 'appointment_id' => null, 'location_id' => $record->member->home_location_id,
        'summary_for_member' => null, 'sections' => ['subjective' => 'Refiere dolor al flexionar', 'plan' => 'Terapia manual'],
    ]);
}

describe('expediente y equipo tratante', function () {
    it('quien abre la historia queda como responsable y entran los profesionales con citas clínicas', function () {
        book($this->member, $this->service, $this->location, $this->otherPhysio, '2026-10-06 09:00', $this->reception);

        $record = openRecordFor($this->member, $this->physio);

        expect($record->primary_staff_id)->toBe(staffOf($this->physio)->id)
            ->and($record->activeTeam()->pluck('staff_id')->sort()->values()->all())
            ->toBe(collect([staffOf($this->physio)->id, staffOf($this->otherPhysio)->id])->sort()->values()->all())
            ->and(ClinicalAccessLog::query()->where('action', ClinicalAction::Create)->exists())->toBeTrue();
    });

    it('agendar una cita clínica con otro profesional lo suma al equipo', function () {
        $record = openRecordFor($this->member, $this->physio);
        expect($this->otherPhysio->can('view', $record))->toBeFalse();

        book($this->member, $this->service, $this->location, $this->otherPhysio, '2026-10-06 09:00', $this->reception);

        expect($this->otherPhysio->can('view', $record->fresh()))->toBeTrue();
    });

    it('solo el equipo ve el contenido; recepción, gerencia y administración ven el resumen', function () {
        $record = openRecordFor($this->member, $this->physio);

        expect($this->physio->can('view', $record))->toBeTrue()
            ->and($this->otherPhysio->can('view', $record))->toBeFalse();

        foreach ([$this->reception, $this->manager, $this->admin, $this->superAdmin] as $user) {
            expect($user->can('view', $record))->toBeFalse()
                ->and($user->can('viewSummary', [PhysiotherapyRecord::class, $this->member]))->toBeTrue();
        }

        $this->actingAs($this->reception)->get(route('admin.clinical.show', $this->member))->assertForbidden();
        $this->actingAs($this->otherPhysio)->get(route('admin.clinical.show', $this->member))->assertForbidden();
        $this->actingAs($this->physio)->get(route('admin.clinical.show', $this->member))->assertOk()->assertSee('Hernia L5');
    });

    it('al retirar a un profesional pierde el acceso de inmediato', function () {
        $record = openRecordFor($this->member, $this->physio);
        app(ClinicalTeam::class)->add($record, staffOf($this->otherPhysio), $this->physio);
        expect($this->otherPhysio->can('view', $record))->toBeTrue();

        Livewire::actingAs($this->reception)->test(ClinicalTeamComponent::class, ['recordId' => $record->id])
            ->call('revoke', staffOf($this->otherPhysio)->id)->assertForbidden();

        Livewire::actingAs($this->manager)->test(ClinicalTeamComponent::class, ['recordId' => $record->id])
            ->call('revoke', staffOf($this->otherPhysio)->id);

        expect($this->otherPhysio->fresh()->can('view', $record->fresh()))->toBeFalse();
    });

    it('cada apertura de la historia queda en la bitácora', function () {
        openRecordFor($this->member, $this->physio);

        Livewire::actingAs($this->physio)->test(ClinicalRecordShow::class, ['member' => $this->member]);

        expect(ClinicalAccessLog::query()->where('action', ClinicalAction::View)->where('user_id', $this->physio->id)->count())->toBe(1);
    });

    it('los campos clínicos se guardan cifrados', function () {
        $record = openRecordFor($this->member, $this->physio);
        $note = sessionNote($record, $this->physio);

        $raw = DB::table('physiotherapy_records')->where('id', $record->id)->first();
        $rawNote = DB::table('clinical_notes')->where('id', $note->id)->value('body');

        expect($raw->medical_history)->not->toContain('Hernia')
            ->and($raw->allergies)->not->toContain('Penicilina')
            ->and($rawNote)->not->toContain('dolor')
            ->and($record->fresh()->medical_history)->toBe('Hernia L5');
    });

    it('la pestaña de fisioterapia abre la historia y no muestra contenido clínico', function () {
        Livewire::actingAs($this->physio)->test(MemberPhysiotherapy::class, ['memberId' => $this->member->id])
            ->call('openForm')
            ->set('reason', 'Dolor de hombro')
            ->call('open')
            ->assertRedirect(route('admin.clinical.show', $this->member));

        Livewire::actingAs($this->reception)->test(MemberPhysiotherapy::class, ['memberId' => $this->member->id])
            ->assertSee('Historia clínica de fisioterapia')
            ->assertDontSee('Dolor de hombro')
            ->call('openForm')->assertForbidden();
    });

    it('un cliente con historia clínica no se puede eliminar', function () {
        openRecordFor($this->member, $this->physio);

        expect(fn () => app(DeleteMember::class)->execute($this->member))->toThrow(ValidationException::class, 'historia clínica');
    });
});

describe('notas y firma', function () {
    beforeEach(function () {
        $this->record = openRecordFor($this->member, $this->physio);
        app(ClinicalTeam::class)->add($this->record, staffOf($this->otherPhysio), $this->physio);
    });

    it('la nota queda sin firmar y solo su autor la edita y firma', function () {
        $note = sessionNote($this->record, $this->physio);

        expect($note->isSigned())->toBeFalse()
            ->and($this->physio->can('update', $note))->toBeTrue()
            ->and($this->otherPhysio->can('update', $note))->toBeFalse()
            ->and($this->otherPhysio->can('sign', $note))->toBeFalse();
    });

    it('firma con la contraseña y después la nota no cambia', function () {
        $note = sessionNote($this->record, $this->physio);

        expect(fn () => app(SignClinicalNote::class)->execute($note, $this->physio, 'incorrecta'))
            ->toThrow(ValidationException::class, 'contraseña no es correcta');

        app(SignClinicalNote::class)->execute($note->fresh(), $this->physio, 'password');

        $note = $note->fresh();
        expect($note->isSigned())->toBeTrue()
            ->and($note->signed_by)->toBe(staffOf($this->physio)->id)
            ->and($this->physio->can('update', $note))->toBeFalse()
            ->and(ClinicalAccessLog::query()->where('action', ClinicalAction::Sign)->exists())->toBeTrue();
    });

    it('la evaluación inicial exige el consentimiento clínico vigente', function () {
        $evaluation = sessionNote($this->record, $this->physio, SessionType::InitialEvaluation);
        expect($evaluation->type)->toBe(NoteType::Evaluation);

        expect(fn () => app(SignClinicalNote::class)->execute($evaluation, $this->physio, 'password'))
            ->toThrow(ValidationException::class, 'consentimiento');

        clinicalConsent($this->member, $this->reception);
        app(SignClinicalNote::class)->execute($evaluation->fresh(), $this->physio, 'password');

        expect($evaluation->fresh()->isSigned())->toBeTrue();
    });

    it('las correcciones a una nota firmada son adendas firmadas', function () {
        $note = sessionNote($this->record, $this->physio);

        expect(fn () => app(AddClinicalAddendum::class)->execute($note, $this->physio, 'Corrección', 'password'))
            ->toThrow(ValidationException::class, 'firmadas');

        app(SignClinicalNote::class)->execute($note, $this->physio, 'password');
        $addendum = app(AddClinicalAddendum::class)->execute($note->fresh(), $this->otherPhysio, 'El dolor era 7/10', 'password');

        expect($addendum->type)->toBe(NoteType::Addendum)
            ->and($addendum->isSigned())->toBeTrue()
            ->and($addendum->parent_note_id)->toBe($note->id)
            ->and($note->fresh()->body['subjective'])->toBe('Refiere dolor al flexionar');
    });

    it('registra y firma una sesión desde la pantalla', function () {
        Livewire::actingAs($this->physio)->test(ClinicalRecordShow::class, ['member' => $this->member])
            ->call('newSession')
            ->set('sessionType', 'treatment')
            ->set('painScale', '4')
            ->set('sections.subjective', 'Mejoría')
            ->set('signPassword', 'password')
            ->call('saveSession', true)
            ->assertHasNoErrors();

        $note = ClinicalNote::query()->sole();
        expect($note->isSigned())->toBeTrue()->and($note->session->pain_scale)->toBe(4);
    });

    it('el panel de la cita lleva a la nota clínica tras marcarla atendida', function () {
        $appointment = book($this->member, $this->service, $this->location, $this->physio, '2026-10-05 09:00', $this->reception);
        $this->travelTo(CarbonImmutable::parse('2026-10-05 10:05', 'America/Bogota'));

        Livewire::actingAs($this->physio)->test(AppointmentPanel::class)
            ->call('open', $appointment->id)
            ->call('mark', 'completed')
            ->assertSet('show', true)
            ->assertSee('Registrar la nota clínica de esta sesión');

        Livewire::actingAs($this->physio)->test(ClinicalRecordShow::class, ['member' => $this->member])
            ->set('appointmentParam', (string) $appointment->id)
            ->call('newSession', $appointment->id)
            ->assertSet('sessionAppointmentId', (string) $appointment->id);
    });

    it('el inicio recuerda las notas sin firmar de más de 24 horas', function () {
        sessionNote($this->record, $this->physio);
        $this->travel(25)->hours();

        $this->actingAs($this->physio)->get(route('admin.dashboard'))->assertSee('notas clínicas sin firmar');
    });
});

describe('acceso de emergencia y exportación', function () {
    beforeEach(function () {
        $this->record = openRecordFor($this->member, $this->physio);
    });

    it('el super admin abre la historia por 2 horas con motivo y se avisa al responsable', function () {
        expect($this->superAdmin->can('view', $this->record))->toBeFalse()
            ->and($this->admin->can('emergency', $this->record))->toBeFalse();

        $this->actingAs($this->superAdmin)->get(route('admin.clinical.show', $this->member))->assertOk()->assertSee('acceso de emergencia');

        expect(fn () => app(GrantEmergencyAccess::class)->execute($this->record, $this->superAdmin, 'corto'))
            ->toThrow(ValidationException::class);

        app(GrantEmergencyAccess::class)->execute($this->record, $this->superAdmin, 'Requerimiento de la EPS del paciente');

        expect($this->superAdmin->can('view', $this->record))->toBeTrue()
            ->and($this->superAdmin->can('write', $this->record))->toBeFalse();
        Notification::assertSentTo($this->physio, EmergencyAccessNotification::class);

        $this->travel(3)->hours();
        expect($this->superAdmin->can('view', $this->record))->toBeFalse();
    });

    it('exporta el PDF con permiso y lo registra', function () {
        $this->actingAs($this->physio)->get(route('admin.clinical.pdf', $this->member))->assertForbidden();

        app(GrantEmergencyAccess::class)->execute($this->record, $this->superAdmin, 'Solicitud formal del paciente');
        $response = $this->actingAs($this->superAdmin)->get(route('admin.clinical.pdf', $this->member));

        $response->assertOk();
        expect($response->headers->get('content-type'))->toContain('application/pdf')
            ->and(ClinicalAccessLog::query()->where('action', ClinicalAction::Export)->exists())->toBeTrue();
    });

    it('los documentos clínicos solo los ve el equipo', function () {
        $document = Document::query()->create([
            'documentable_type' => 'member', 'documentable_id' => $this->member->id, 'member_id' => $this->member->id,
            'category' => 'medical', 'sensitivity' => 'clinical', 'title' => 'Resonancia', 'disk' => 'local',
            'path' => 'documents/x.pdf', 'original_name' => 'x.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 10,
            'checksum' => str_repeat('a', 64), 'uploaded_by' => $this->physio->id,
        ]);

        expect($this->physio->can('view', $document))->toBeTrue()
            ->and($this->otherPhysio->can('view', $document))->toBeFalse()
            ->and($this->manager->can('view', $document))->toBeFalse();
    });
});
