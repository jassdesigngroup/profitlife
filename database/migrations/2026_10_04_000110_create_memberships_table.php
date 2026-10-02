<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members');
            $table->foreignId('membership_plan_id')->constrained('membership_plans');
            $table->foreignId('purchase_location_id')->constrained('locations');
            $table->string('status', 30); // pending | active | frozen | suspended | expired | cancelled
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->date('next_billing_on')->nullable();
            $table->bigInteger('price_cents'); // copia del precio al contratar
            $table->char('currency', 3);
            $table->boolean('auto_renews');
            $table->foreignId('renewed_from_id')->nullable()->constrained('memberships');
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancellation_reason', 255)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->datetimes();
            $table->softDeletesDatetime();

            $table->index(['member_id', 'status']);
            $table->index(['status', 'ends_on']);
            $table->index('next_billing_on');
        });

        Schema::create('membership_freezes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_id')->constrained('memberships')->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on')->nullable(); // nulo = congelación abierta
            $table->string('reason', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->datetimes();
        });

        // Solo inserción.
        Schema::create('membership_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_id')->constrained('memberships')->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->string('reason', 255)->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users'); // nulo = cambio automático (job)
            $table->dateTime('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_status_histories');
        Schema::dropIfExists('membership_freezes');
        Schema::dropIfExists('memberships');
    }
};
