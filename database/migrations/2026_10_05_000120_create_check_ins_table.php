<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Solo inserción: registra los ingresos aceptados y los rechazados.
        Schema::create('check_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->nullable()->constrained('members'); // nulo = intento sin identificar
            $table->foreignId('location_id')->constrained('locations');
            $table->foreignId('membership_id')->nullable()->constrained('memberships');
            $table->foreignId('kiosk_device_id')->nullable()->constrained('kiosk_devices');
            $table->foreignId('registered_by')->nullable()->constrained('users'); // check-in manual en recepción
            $table->string('method', 30); // qr | phone | member_number | membership_code | manual
            $table->string('result', 30); // accepted | rejected
            $table->string('rejection_reason', 40)->nullable();
            $table->dateTime('checked_in_at');
            $table->dateTime('created_at')->nullable();

            $table->index(['location_id', 'checked_in_at']);
            $table->index(['member_id', 'checked_in_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('check_ins');
    }
};
