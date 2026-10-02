<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Solo inserción: trazabilidad de envíos.
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->string('notifiable_type', 100);
            $table->unsignedBigInteger('notifiable_id');
            $table->string('notification_type', 60);
            $table->string('channel', 20);
            $table->string('recipient', 190);
            $table->string('status', 20);
            $table->string('provider_message_id', 120)->nullable();
            $table->text('error')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->index(['notifiable_type', 'notifiable_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
