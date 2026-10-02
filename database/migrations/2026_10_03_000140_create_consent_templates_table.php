<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consent_templates', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30);
            $table->string('title', 150);
            $table->longText('body');
            $table->smallInteger('version');
            $table->boolean('is_active')->default(true);
            $table->datetimes();

            $table->unique(['type', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_templates');
    }
};
