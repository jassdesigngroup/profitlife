<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique(); // identificador en URLs de descarga
            $table->string('documentable_type', 100);
            $table->unsignedBigInteger('documentable_id');
            $table->foreignId('member_id')->nullable()->constrained('members'); // acelera la comprobación de la Policy
            $table->string('category', 30);
            $table->string('sensitivity', 20);
            $table->string('title', 150);
            $table->string('disk', 30);
            $table->string('path', 255);
            $table->string('original_name', 255);
            $table->string('mime_type', 100);
            $table->bigInteger('size_bytes');
            $table->char('checksum', 64);
            $table->boolean('is_visible_to_member')->default(false);
            $table->foreignId('uploaded_by')->nullable()->constrained('users');
            $table->datetimes();
            $table->softDeletesDatetime();

            $table->index(['documentable_type', 'documentable_id']);
            $table->index(['member_id', 'sensitivity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
