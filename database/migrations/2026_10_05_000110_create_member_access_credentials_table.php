<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_access_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->string('type', 30); // qr | membership_code
            $table->char('token_hash', 64)->unique(); // SHA-256; es lo que se busca al escanear
            $table->text('token_encrypted'); // cifrado: permite volver a mostrar el QR
            $table->boolean('is_active')->default(true);
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('last_used_at')->nullable();
            $table->datetimes();

            $table->index(['member_id', 'type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_access_credentials');
    }
};
