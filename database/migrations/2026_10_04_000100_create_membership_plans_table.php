<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 120)->unique();
            $table->text('description')->nullable();
            $table->string('duration_unit', 10)->nullable(); // day | week | month | year; nulo = indefinida
            $table->smallInteger('duration_count')->nullable();
            $table->string('billing_unit', 10); // frecuencia de cobro
            $table->smallInteger('billing_count');
            $table->bigInteger('price_cents');
            $table->bigInteger('enrollment_fee_cents')->default(0);
            $table->char('currency', 3);
            $table->smallInteger('tax_rate_bps')->default(0);
            $table->string('access_scope', 30); // all_locations | selected_locations
            $table->smallInteger('visit_limit_count')->nullable(); // nulo = visitas ilimitadas
            $table->string('visit_limit_period', 10)->nullable(); // week | month | term
            $table->smallInteger('max_freeze_days')->nullable();
            $table->boolean('auto_renews')->default(false);
            $table->json('benefits')->nullable();
            $table->boolean('is_active')->default(true);
            $table->smallInteger('sort_order')->default(0);
            $table->datetimes();
            $table->softDeletesDatetime();
        });

        // Sedes donde el plan es válido.
        Schema::create('location_membership_plan', function (Blueprint $table) {
            $table->foreignId('membership_plan_id')->constrained('membership_plans')->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();

            $table->primary(['membership_plan_id', 'location_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_membership_plan');
        Schema::dropIfExists('membership_plans');
    }
};
