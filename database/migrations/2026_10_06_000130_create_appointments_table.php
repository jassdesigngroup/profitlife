<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members');
            $table->foreignId('staff_id')->constrained('staff');
            $table->foreignId('service_id')->constrained('services');
            $table->foreignId('location_id')->constrained('locations');
            $table->foreignId('room_id')->nullable()->constrained('rooms');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status', 30); // pending | confirmed | completed | cancelled | no_show | rescheduled
            $table->string('source', 20); // admin | portal
            $table->text('notes')->nullable(); // administrativas; lo clínico va en otro módulo
            $table->bigInteger('price_cents')->nullable(); // copia del precio al reservar
            $table->foreignId('rescheduled_from_id')->nullable()->constrained('appointments');
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users');
            $table->string('cancellation_reason', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->datetimes();
            $table->softDeletesDatetime();

            $table->index(['staff_id', 'starts_at', 'ends_at']);
            $table->index(['room_id', 'starts_at', 'ends_at']);
            $table->index(['location_id', 'starts_at']);
            $table->index(['member_id', 'starts_at']);
            $table->index('status');
        });

        // Solo inserción.
        Schema::create('appointment_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->string('reason', 255)->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users');
            $table->dateTime('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_status_histories');
        Schema::dropIfExists('appointments');
    }
};
