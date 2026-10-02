<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sesiones de servicios incluidas en un plan (planes con sesiones y paquetes).
        Schema::create('membership_plan_service', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_plan_id')->constrained('membership_plans')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services');
            $table->smallInteger('sessions_included')->nullable(); // nulo = ilimitadas
            $table->string('period', 20); // per_billing_period | per_term

            $table->unique(['membership_plan_id', 'service_id']);
        });

        // Decisión de la Fase 6: un paquete de sesiones puede no dar acceso al gimnasio.
        Schema::table('membership_plans', function (Blueprint $table) {
            $table->boolean('includes_gym_access')->default(true)->after('access_scope');
        });
    }

    public function down(): void
    {
        Schema::table('membership_plans', function (Blueprint $table) {
            $table->dropColumn('includes_gym_access');
        });

        Schema::dropIfExists('membership_plan_service');
    }
};
