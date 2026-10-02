<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 120)->unique();
            $table->string('category', 30); // physiotherapy | personal_training | assessment | other
            $table->text('description')->nullable();
            $table->smallInteger('duration_minutes');
            $table->smallInteger('buffer_minutes')->default(0); // margen entre citas
            $table->bigInteger('price_cents');
            $table->char('currency', 3);
            $table->smallInteger('tax_rate_bps')->default(0);
            $table->boolean('requires_room')->default(false);
            $table->boolean('is_clinical')->default(false); // activa las reglas de privacidad
            $table->boolean('is_bookable_online')->default(false);
            $table->char('color', 7)->nullable();
            $table->boolean('is_active')->default(true);
            $table->datetimes();
            $table->softDeletesDatetime();
        });

        // Servicios disponibles por sede, con precio propio opcional.
        Schema::create('location_service', function (Blueprint $table) {
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->bigInteger('price_cents')->nullable(); // nulo = el del servicio
            $table->boolean('is_active')->default(true);

            $table->primary(['location_id', 'service_id']);
        });

        // Quién puede prestar cada servicio.
        Schema::create('service_staff', function (Blueprint $table) {
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();

            $table->primary(['service_id', 'staff_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_staff');
        Schema::dropIfExists('location_service');
        Schema::dropIfExists('services');
    }
};
