<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sedes de cada empleado: definen su alcance de datos.
        Schema::create('location_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained('locations');
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->datetimes();

            $table->unique(['location_id', 'staff_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_staff');
    }
};
