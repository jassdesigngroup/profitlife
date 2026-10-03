<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_programs', function (Blueprint $table) {
            $table->id();
            // Decisión de la Fase 8: nulo en las plantillas (is_template = true).
            $table->foreignId('member_id')->nullable()->constrained('members');
            $table->foreignId('staff_id')->constrained('staff');
            $table->foreignId('treatment_plan_id')->nullable()->constrained('treatment_plans');
            $table->string('type', 20); // training | rehab
            $table->boolean('is_template')->default(false);
            $table->string('name', 150);
            $table->text('goal')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('status', 30); // draft | active | completed | archived
            $table->datetimes();
            $table->softDeletesDatetime();

            $table->index(['member_id', 'status']);
        });

        // Una rutina o sesión del programa.
        Schema::create('workouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_program_id')->constrained('training_programs')->cascadeOnDelete();
            $table->string('name', 150);
            $table->date('scheduled_on')->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->datetimes();
        });

        Schema::create('workout_exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workout_id')->constrained('workouts')->cascadeOnDelete();
            $table->foreignId('exercise_id')->constrained('exercises');
            $table->smallInteger('sort_order')->default(0);
            $table->tinyInteger('superset_group')->nullable();
            $table->text('notes')->nullable();
        });

        // Lo prescrito.
        Schema::create('workout_exercise_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workout_exercise_id')->constrained('workout_exercises')->cascadeOnDelete();
            $table->tinyInteger('set_number');
            $table->smallInteger('reps')->nullable();
            $table->decimal('weight_kg', 6, 2)->nullable();
            $table->smallInteger('duration_seconds')->nullable();
            $table->integer('distance_meters')->nullable();
            $table->smallInteger('rest_seconds')->nullable();
            $table->decimal('rpe', 3, 1)->nullable();
            $table->string('notes', 255)->nullable();
        });

        // Lo realizado.
        Schema::create('workout_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workout_id')->constrained('workouts');
            $table->foreignId('member_id')->constrained('members');
            $table->foreignId('logged_by')->constrained('users');
            $table->dateTime('performed_at');
            $table->smallInteger('duration_minutes')->nullable();
            $table->decimal('rpe', 3, 1)->nullable();
            $table->text('notes')->nullable();
            $table->datetimes();

            $table->index(['member_id', 'performed_at']);
        });

        Schema::create('workout_log_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workout_log_id')->constrained('workout_logs')->cascadeOnDelete();
            $table->foreignId('workout_exercise_id')->constrained('workout_exercises');
            $table->tinyInteger('set_number');
            $table->smallInteger('reps')->nullable();
            $table->decimal('weight_kg', 6, 2)->nullable();
            $table->smallInteger('duration_seconds')->nullable();
            $table->integer('distance_meters')->nullable();
            $table->decimal('rpe', 3, 1)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_log_sets');
        Schema::dropIfExists('workout_logs');
        Schema::dropIfExists('workout_exercise_sets');
        Schema::dropIfExists('workout_exercises');
        Schema::dropIfExists('workouts');
        Schema::dropIfExists('training_programs');
    }
};
