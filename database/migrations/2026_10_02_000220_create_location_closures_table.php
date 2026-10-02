<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Festivos y cierres puntuales. location_id nulo = todas las sedes.
        Schema::create('location_closures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->nullable()->constrained('locations')->cascadeOnDelete();
            $table->date('closed_on');
            $table->string('reason', 150)->nullable();
            $table->datetimes();

            $table->index('closed_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_closures');
    }
};
