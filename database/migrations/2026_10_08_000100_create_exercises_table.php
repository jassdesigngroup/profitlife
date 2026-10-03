<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('muscle_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 80)->unique();
        });

        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 80)->unique();
        });

        Schema::create('exercises', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('slug', 150)->unique();
            $table->text('description')->nullable();
            $table->text('instructions')->nullable();
            $table->string('video_url', 255)->nullable();
            $table->string('image_path', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->datetimes();
            $table->softDeletesDatetime();
        });

        Schema::create('exercise_muscle_group', function (Blueprint $table) {
            $table->foreignId('exercise_id')->constrained('exercises')->cascadeOnDelete();
            $table->foreignId('muscle_group_id')->constrained('muscle_groups')->cascadeOnDelete();
            $table->boolean('is_primary')->default(true);

            $table->primary(['exercise_id', 'muscle_group_id']);
        });

        Schema::create('equipment_exercise', function (Blueprint $table) {
            $table->foreignId('exercise_id')->constrained('exercises')->cascadeOnDelete();
            $table->foreignId('equipment_id')->constrained('equipment')->cascadeOnDelete();

            $table->primary(['exercise_id', 'equipment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_exercise');
        Schema::dropIfExists('exercise_muscle_group');
        Schema::dropIfExists('exercises');
        Schema::dropIfExists('equipment');
        Schema::dropIfExists('muscle_groups');
    }
};
