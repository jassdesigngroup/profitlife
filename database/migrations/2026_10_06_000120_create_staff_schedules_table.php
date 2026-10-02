<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Disponibilidad semanal por sede. Horas locales de la sede.
        Schema::create('staff_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('locations');
            $table->tinyInteger('day_of_week'); // ISO: 1 = lunes … 7 = domingo
            $table->time('starts_at');
            $table->time('ends_at');
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->datetimes();

            $table->index(['staff_id', 'day_of_week']);
        });

        Schema::create('staff_time_off', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('reason', 150)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->datetimes();

            $table->index(['staff_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_time_off');
        Schema::dropIfExists('staff_schedules');
    }
};
