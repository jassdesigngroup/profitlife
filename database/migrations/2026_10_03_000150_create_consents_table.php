<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sirve a todos los módulos, no solo a fisioterapia.
        Schema::create('consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members');
            $table->foreignId('consent_template_id')->constrained('consent_templates');
            $table->string('method', 20);
            $table->string('signed_name', 150); // el cliente o su acudiente
            $table->foreignId('document_id')->nullable()->constrained('documents'); // PDF o escaneo firmado
            $table->dateTime('accepted_at');
            $table->dateTime('revoked_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->foreignId('captured_by')->nullable()->constrained('users');
            $table->datetimes();

            $table->index(['member_id', 'consent_template_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consents');
    }
};
