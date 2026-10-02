<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Un expediente por cliente. Los campos clínicos van cifrados (texto).
        Schema::create('physiotherapy_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->unique()->constrained('members');
            $table->foreignId('primary_staff_id')->nullable()->constrained('staff');
            $table->string('status', 30); // active | discharged
            $table->text('reason_for_consultation')->nullable();
            $table->longText('medical_history')->nullable();
            $table->text('medications')->nullable();
            $table->text('allergies')->nullable();
            $table->dateTime('opened_at');
            $table->foreignId('opened_by')->constrained('users');
            $table->datetimes();
        });

        // Equipo tratante: define "sus pacientes".
        Schema::create('physiotherapy_record_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('physiotherapy_record_id')->constrained('physiotherapy_records')->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff');
            $table->foreignId('granted_by')->constrained('users');
            $table->dateTime('granted_at');
            $table->dateTime('revoked_at')->nullable();

            $table->unique(['physiotherapy_record_id', 'staff_id'], 'physio_record_staff_unique');
        });

        Schema::create('treatment_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('physiotherapy_record_id')->constrained('physiotherapy_records');
            $table->foreignId('staff_id')->constrained('staff');
            $table->string('title', 150);
            $table->text('diagnosis')->nullable();
            $table->text('goals')->nullable();
            $table->smallInteger('planned_sessions')->nullable();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('status', 30); // draft | active | completed | cancelled
            $table->boolean('is_visible_to_member')->default(false);
            $table->datetimes();
            $table->softDeletesDatetime();
        });

        Schema::create('physiotherapy_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('physiotherapy_record_id')->constrained('physiotherapy_records');
            $table->foreignId('treatment_plan_id')->nullable()->constrained('treatment_plans');
            $table->foreignId('appointment_id')->nullable()->unique()->constrained('appointments');
            $table->foreignId('staff_id')->constrained('staff');
            $table->foreignId('location_id')->constrained('locations');
            $table->string('session_type', 30); // initial_evaluation | treatment | follow_up | discharge
            $table->dateTime('performed_at');
            $table->tinyInteger('pain_scale')->nullable(); // 0 a 10
            $table->text('summary_for_member')->nullable(); // lo único que ve el cliente en el portal
            $table->datetimes();

            $table->index(['physiotherapy_record_id', 'performed_at'], 'physio_sessions_record_performed_index');
        });

        // Sin soft delete; inmutable al firmarse.
        Schema::create('clinical_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('physiotherapy_record_id')->constrained('physiotherapy_records');
            $table->foreignId('physiotherapy_session_id')->nullable()->constrained('physiotherapy_sessions');
            $table->foreignId('author_id')->constrained('staff');
            $table->string('type', 30); // evaluation | progress | soap | addendum
            $table->foreignId('parent_note_id')->nullable()->constrained('clinical_notes');
            $table->longText('body');
            $table->dateTime('signed_at')->nullable();
            $table->foreignId('signed_by')->nullable()->constrained('staff');
            $table->boolean('is_visible_to_member')->default(false);
            $table->datetimes();

            $table->index(['physiotherapy_record_id', 'created_at']);
        });

        // Solo inserción.
        Schema::create('clinical_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('member_id')->constrained('members');
            $table->string('subject_type', 100);
            $table->unsignedBigInteger('subject_id');
            $table->string('action', 20); // view | create | update | sign | download | export | emergency
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->dateTime('created_at')->nullable();

            $table->index(['member_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_access_logs');
        Schema::dropIfExists('clinical_notes');
        Schema::dropIfExists('physiotherapy_sessions');
        Schema::dropIfExists('treatment_plans');
        Schema::dropIfExists('physiotherapy_record_staff');
        Schema::dropIfExists('physiotherapy_records');
    }
};
