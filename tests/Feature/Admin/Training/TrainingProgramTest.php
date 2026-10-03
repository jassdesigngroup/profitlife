<?php

use App\Domain\Identity\Enums\RoleName;
use App\Domain\Identity\Models\User;
use App\Domain\Locations\Models\Location;
use App\Domain\Members\Models\Member;
use App\Domain\Physiotherapy\Actions\OpenPhysiotherapyRecord;
use App\Domain\Training\Actions\DuplicateTrainingProgram;
use App\Domain\Training\Actions\LogWorkout;
use App\Domain\Training\Actions\SaveProgramStructure;
use App\Domain\Training\Actions\SaveTrainingProgram;
use App\Domain\Training\Enums\ProgramStatus;
use App\Domain\Training\Enums\ProgramType;
use App\Domain\Training\Models\Exercise;
use App\Domain\Training\Models\MuscleGroup;
use App\Domain\Training\Models\TrainingProgram;
use App\Domain\Training\Models\WorkoutLog;
use App\Domain\Training\Notifications\TrainingProgramNotification;
use App\Domain\Training\Services\ExerciseProgress;
use App\Livewire\Admin\Clinical\ClinicalHomeExercises;
use App\Livewire\Admin\Members\MemberTraining;
use App\Livewire\Admin\Training\ExerciseLibrary;
use App\Livewire\Admin\Training\ProgramEditor;
use App\Livewire\Admin\Training\TemplateIndex;
use App\Livewire\Admin\Training\WorkoutLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-10-05 07:00', 'America/Bogota'));
    $this->location = Location::factory()->create();
    $this->otherLocation = Location::factory()->create();
    $this->trainer = staffUser(RoleName::Trainer, [$this->location]);
    $this->otherTrainer = staffUser(RoleName::Trainer, [$this->otherLocation]);
    $this->physio = staffUser(RoleName::Physiotherapist, [$this->location]);
    $this->reception = staffUser(RoleName::Reception, [$this->location]);
    $this->member = memberAt($this->location, ['first_name' => 'Laura', 'last_name' => 'Quintero', 'email' => 'laura@example.com']);
    $this->squat = trainingExercise('Sentadilla');
    $this->row = trainingExercise('Remo');
});

function trainingExercise(string $name): Exercise
{
    $exercise = Exercise::query()->create(['name' => $name, 'slug' => str($name)->slug(), 'is_active' => true]);
    $group = MuscleGroup::query()->firstOrCreate(['slug' => 'pierna'], ['name' => 'Pierna']);
    $exercise->muscleGroups()->attach($group->id, ['is_primary' => true]);

    return $exercise;
}

function programFor(?Member $member, User $actor, array $exercises, ProgramType $type = ProgramType::Training, ?int $planId = null): TrainingProgram
{
    $program = app(SaveTrainingProgram::class)->create($member, $type, [
        'name' => 'Fuerza', 'goal' => null, 'starts_on' => null, 'ends_on' => null, 'status' => ProgramStatus::Active, 'treatment_plan_id' => $planId,
    ], $actor);

    app(SaveProgramStructure::class)->execute($program, [[
        'id' => null, 'name' => 'Día A', 'notes' => null,
        'exercises' => array_map(fn (Exercise $e) => [
            'id' => null, 'exercise_id' => $e->id, 'superset_group' => null, 'notes' => null,
            'sets' => [['reps' => '10', 'weight_kg' => '40', 'rest_seconds' => '90'], ['reps' => '8', 'weight_kg' => '45', 'rest_seconds' => '90']],
        ], $exercises),
    ]]);

    return $program->fresh();
}

/**
 * @param  array<int, list<array<string, mixed>>>  $sets
 */
function logFor(TrainingProgram $program, User $actor, array $sets, string $when = '-1 hour'): WorkoutLog
{
    $workout = $program->workouts()->first();

    return app(LogWorkout::class)->execute($program, $workout, $actor, [
        'performed_at' => CarbonImmutable::now()->modify($when), 'duration_minutes' => 50, 'rpe' => 7.0, 'notes' => null, 'sets' => $sets,
    ]);
}

describe('biblioteca de ejercicios', function () {
    it('el entrenador crea ejercicios; recepción no tiene acceso', function () {
        $group = MuscleGroup::query()->firstOrFail();

        Livewire::actingAs($this->trainer)->test(ExerciseLibrary::class)
            ->call('create')
            ->set('name', 'Hip thrust')
            ->set('videoUrl', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ')
            ->set('primary', [(string) $group->id])
            ->call('save')
            ->assertHasNoErrors();

        $exercise = Exercise::query()->where('name', 'Hip thrust')->firstOrFail();
        expect($exercise->embedUrl())->toBe('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ')
            ->and($exercise->muscleGroups()->wherePivot('is_primary', true)->count())->toBe(1);

        $this->actingAs($this->reception)->get(route('admin.training.exercises'))->assertForbidden();
    });

    it('solo acepta videos de YouTube o Vimeo y exige un grupo muscular', function () {
        Livewire::actingAs($this->trainer)->test(ExerciseLibrary::class)
            ->call('create')
            ->set('name', 'Otro')
            ->set('videoUrl', 'https://evil.example.com/video')
            ->call('save')
            ->assertHasErrors(['videoUrl', 'primary']);
    });

    it('el fisioterapeuta no puede eliminar ejercicios (sin permiso)', function () {
        Livewire::actingAs($this->physio)->test(ExerciseLibrary::class)
            ->call('delete', $this->squat->id)
            ->assertForbidden();

        expect($this->squat->fresh()->trashed())->toBeFalse();
    });
});

describe('programas y acceso por sede', function () {
    it('el entrenador ve y edita programas de clientes de sus sedes, no de otras', function () {
        $program = programFor($this->member, $this->trainer, [$this->squat]);

        expect($this->trainer->can('update', $program))->toBeTrue()
            ->and($this->otherTrainer->can('view', $program))->toBeFalse()
            ->and($this->reception->can('view', $program))->toBeFalse();

        $this->actingAs($this->trainer)->get(route('admin.training.programs.edit', $program))->assertOk()->assertSee('Sentadilla');
        $this->actingAs($this->otherTrainer)->get(route('admin.training.programs.edit', $program))->assertForbidden();
    });

    it('el editor guarda la estructura con superseries y reordena', function () {
        $program = programFor($this->member, $this->trainer, [$this->squat]);

        $component = Livewire::actingAs($this->trainer)->test(ProgramEditor::class, ['program' => $program])
            ->call('openPicker', 0)
            ->call('pickExercise', $this->row->id)
            ->set('workouts.0.exercises.0.superset_group', '1')
            ->set('workouts.0.exercises.1.superset_group', '1')
            ->call('moveExercise', 0, 1, -1)
            ->call('addWorkout')
            ->set('workouts.1.name', 'Día B')
            ->call('save')
            ->assertHasNoErrors();

        $workouts = $program->workouts()->orderBy('sort_order')->with('exercises.sets')->get();
        expect($workouts)->toHaveCount(2)
            ->and($workouts[0]->exercises->pluck('exercise_id')->all())->toBe([$this->row->id, $this->squat->id])
            ->and($workouts[0]->exercises->pluck('superset_group')->all())->toBe([1, 1])
            ->and($workouts[0]->exercises[0]->sets)->toHaveCount(3)
            ->and($workouts[1]->name)->toBe('Día B');

        $component->assertSet('dirty', false);
    });

    it('otro entrenador no puede modificar el programa aunque manipule la petición', function () {
        $program = programFor($this->member, $this->trainer, [$this->squat]);

        Livewire::actingAs($this->otherTrainer)->test(ProgramEditor::class, ['program' => $program])->assertForbidden();
    });

    it('no deja quitar ejercicios ni rutinas con entrenamientos registrados', function () {
        $program = programFor($this->member, $this->trainer, [$this->squat]);
        $item = $program->workouts()->first()->exercises()->first();
        logFor($program, $this->trainer, [$item->id => [['reps' => 10, 'weight_kg' => 40]]]);

        Livewire::actingAs($this->trainer)->test(ProgramEditor::class, ['program' => $program])
            ->call('removeExercise', 0, 0)
            ->call('save')
            ->assertHasErrors('workouts');

        Livewire::actingAs($this->trainer)->test(ProgramEditor::class, ['program' => $program])
            ->call('removeWorkout', 0)
            ->call('save')
            ->assertHasErrors('workouts');

        expect($program->workouts()->count())->toBe(1)->and($item->fresh())->not->toBeNull();
    });
});

describe('plantillas y copias', function () {
    it('crea una plantilla y la asigna a un cliente con una copia independiente', function () {
        Livewire::actingAs($this->trainer)->test(TemplateIndex::class)
            ->call('create')->set('name', 'Hipertrofia')->call('save')
            ->assertRedirect();

        $template = TrainingProgram::query()->where('is_template', true)->firstOrFail();
        expect($template->member_id)->toBeNull();
        app(SaveProgramStructure::class)->execute($template, [[
            'id' => null, 'name' => 'Día A', 'notes' => null,
            'exercises' => [['id' => null, 'exercise_id' => $this->squat->id, 'superset_group' => null, 'notes' => null, 'sets' => [['reps' => '12']]]],
        ]]);

        Livewire::actingAs($this->trainer)->test(MemberTraining::class, ['memberId' => $this->member->id])
            ->call('openCreate')
            ->set('templateId', (string) $template->id)
            ->call('create')
            ->assertHasNoErrors()
            ->assertRedirect();

        $program = TrainingProgram::query()->where('member_id', $this->member->id)->firstOrFail();
        expect($program->is_template)->toBeFalse()
            ->and($program->name)->toBe('Hipertrofia')
            ->and($program->workouts()->first()->exercises()->first()->sets()->value('reps'))->toBe(12);

        // Editar la copia no cambia la plantilla.
        $program->workouts()->first()->update(['name' => 'Cambiado']);
        expect($template->workouts()->first()->name)->toBe('Día A');
    });

    it('copia un programa a otro cliente solo si el usuario puede ver al destino', function () {
        $program = programFor($this->member, $this->trainer, [$this->squat]);
        $sameSite = memberAt($this->location);
        $foreign = memberAt($this->otherLocation);

        Livewire::actingAs($this->trainer)->test(MemberTraining::class, ['memberId' => $this->member->id])
            ->call('openCopy', $program->id)
            ->call('copyTo', $foreign->id)
            ->assertNotFound();

        Livewire::actingAs($this->trainer)->test(MemberTraining::class, ['memberId' => $this->member->id])
            ->call('openCopy', $program->id)
            ->call('copyTo', $sameSite->id)
            ->assertRedirect();

        expect(TrainingProgram::query()->where('member_id', $sameSite->id)->count())->toBe(1)
            ->and(TrainingProgram::query()->withoutGlobalScopes()->where('member_id', $foreign->id)->count())->toBe(0);
    });

    it('guardar como plantilla no copia datos del cliente', function () {
        $program = programFor($this->member, $this->trainer, [$this->squat]);

        $copy = app(DuplicateTrainingProgram::class)->execute($program, null, $this->trainer, 'Base');

        expect($copy->is_template)->toBeTrue()->and($copy->member_id)->toBeNull()->and($copy->starts_on)->toBeNull()
            ->and($copy->workouts()->first()->exercises()->count())->toBe(1);
    });
});

describe('registro de entrenamientos y progreso', function () {
    it('registra desde la pantalla rápida y omite las series no hechas', function () {
        $program = programFor($this->member, $this->trainer, [$this->squat]);
        $item = $program->workouts()->first()->exercises()->first();

        Livewire::actingAs($this->trainer)->test(WorkoutLogger::class, ['program' => $program])
            ->assertSet("sets.{$item->id}.0.reps", '10')
            ->assertSet("sets.{$item->id}.0.weight_kg", '40')
            ->set("sets.{$item->id}.1.done", false)
            ->set('duration', '45')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.members.show', ['member' => $this->member->id, 'tab' => 'training']));

        $log = WorkoutLog::query()->firstOrFail();
        expect($log->sets()->count())->toBe(1)
            ->and($log->duration_minutes)->toBe(45)
            ->and($log->logged_by)->toBe($this->trainer->id);
    });

    it('rechaza series de ejercicios de otra rutina y fechas futuras', function () {
        $program = programFor($this->member, $this->trainer, [$this->squat]);
        $other = programFor($this->member, $this->trainer, [$this->row]);
        $foreignItem = $other->workouts()->first()->exercises()->first();

        expect(fn () => logFor($program, $this->trainer, [$foreignItem->id => [['reps' => 5]]]))->toThrow(ValidationException::class);

        $item = $program->workouts()->first()->exercises()->first();
        expect(fn () => logFor($program, $this->trainer, [$item->id => [['reps' => 5]]], '+2 hours'))->toThrow(ValidationException::class);
    });

    it('no se registran entrenamientos en una plantilla', function () {
        $template = programFor(null, $this->trainer, [$this->squat]);

        expect($this->trainer->can('log', $template))->toBeFalse();
        $this->actingAs($this->trainer)->get(route('admin.training.programs.log', $template))->assertForbidden();
    });

    it('calcula peso máximo y volumen por sesión', function () {
        $program = programFor($this->member, $this->trainer, [$this->squat]);
        $item = $program->workouts()->first()->exercises()->first();
        logFor($program, $this->trainer, [$item->id => [['reps' => 10, 'weight_kg' => 40], ['reps' => 8, 'weight_kg' => 45]]], '-8 days');
        logFor($program, $this->trainer, [$item->id => [['reps' => 10, 'weight_kg' => 50]]], '-1 day');

        $series = app(ExerciseProgress::class)->seriesFor($this->member, $this->squat);

        expect($series)->toHaveCount(2)
            ->and($series[0]->max_weight)->toBe(45.0)
            ->and($series[0]->volume)->toBe(760.0)
            ->and($series[1]->max_weight)->toBe(50.0);

        Livewire::actingAs($this->trainer)->test(MemberTraining::class, ['memberId' => $this->member->id])
            ->assertSee('Sentadilla')
            ->assertSee('50 kg');
    });
});

describe('rehabilitación', function () {
    beforeEach(function () {
        $this->record = app(OpenPhysiotherapyRecord::class)->execute($this->member, $this->physio, [
            'reason_for_consultation' => 'Rodilla', 'medical_history' => null, 'medications' => null, 'allergies' => null,
        ]);
    });

    it('el fisioterapeuta del equipo crea ejercicios para casa desde la historia', function () {
        Livewire::actingAs($this->physio)->test(ClinicalHomeExercises::class, ['recordId' => $this->record->id])
            ->call('create')
            ->set('name', 'Rodilla en casa')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $program = TrainingProgram::query()->where('type', ProgramType::Rehab)->firstOrFail();
        expect($program->member_id)->toBe($this->member->id);
    });

    it('los programas de rehabilitación solo los ve el equipo tratante', function () {
        $rehab = programFor($this->member, $this->physio, [$this->squat], ProgramType::Rehab);
        $training = programFor($this->member, $this->trainer, [$this->row]);

        expect($this->physio->can('view', $rehab))->toBeTrue()
            ->and($this->trainer->can('view', $rehab))->toBeFalse()
            ->and($this->trainer->can('view', $training))->toBeTrue();

        Livewire::actingAs($this->trainer)->test(MemberTraining::class, ['memberId' => $this->member->id])
            ->assertSee($training->name)
            ->assertDontSee('Rehabilitación');

        $this->actingAs($this->trainer)->get(route('admin.training.programs.pdf', $rehab))->assertForbidden();
        Livewire::actingAs($this->trainer)->test(ClinicalHomeExercises::class, ['recordId' => $this->record->id])->assertForbidden();
    });

    it('el entrenador no puede crear programas de rehabilitación', function () {
        expect($this->trainer->can('create', [TrainingProgram::class, $this->member, ProgramType::Rehab]))->toBeFalse()
            ->and($this->physio->can('create', [TrainingProgram::class, $this->member, ProgramType::Rehab]))->toBeTrue();
    });

    it('al copiar un programa de rehabilitación la copia es de entrenamiento y sin plan', function () {
        $rehab = programFor($this->member, $this->physio, [$this->squat], ProgramType::Rehab);

        $copy = app(DuplicateTrainingProgram::class)->execute($rehab, $this->member, $this->physio);

        expect($copy->type)->toBe(ProgramType::Training)->and($copy->treatment_plan_id)->toBeNull();
    });
});

describe('PDF y correo', function () {
    it('descarga el PDF y lo envía por correo con auditoría', function () {
        $program = programFor($this->member, $this->trainer, [$this->squat]);

        $this->actingAs($this->trainer)->get(route('admin.training.programs.pdf', $program))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');

        Livewire::actingAs($this->trainer)->test(ProgramEditor::class, ['program' => $program])->call('send');

        Notification::assertSentTo($this->member, TrainingProgramNotification::class);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'training', 'subject_id' => $program->id]);
    });

    it('no envía si el cliente no tiene correo', function () {
        $member = memberAt($this->location, ['email' => null]);
        $program = programFor($member, $this->trainer, [$this->squat]);

        Livewire::actingAs($this->trainer)->test(ProgramEditor::class, ['program' => $program])
            ->call('send')
            ->assertHasErrors('send');

        Notification::assertNothingSent();
    });
});
