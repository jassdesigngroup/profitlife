<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Libro mayor de sesiones: saldo = SUM(delta). Solo inserción.
        Schema::create('session_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members');
            $table->foreignId('membership_id')->nullable()->constrained('memberships');
            $table->foreignId('service_id')->constrained('services');
            $table->smallInteger('delta'); // +N al otorgar, -1 al consumir
            $table->string('reason', 30); // grant | consume | expire | adjust | refund
            $table->foreignId('appointment_id')->nullable()->constrained('appointments');
            $table->date('expires_on')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->dateTime('created_at')->nullable();

            $table->index(['member_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_credits');
    }
};
