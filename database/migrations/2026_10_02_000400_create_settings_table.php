<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->nullable()->constrained('locations'); // nulo = global
            $table->string('group', 50);
            $table->string('key', 80);
            $table->json('value');
            $table->datetimes();

            // La unicidad se valida en la aplicación: MySQL admite varios NULL en un UNIQUE.
            $table->index(['group', 'key', 'location_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
