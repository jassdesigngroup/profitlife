<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 120)->unique();
            $table->string('code', 10)->unique();
            $table->string('address_line', 255);
            $table->string('city', 100);
            $table->string('department', 100);
            $table->string('phone', 20)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('timezone', 50)->default('America/Bogota');
            $table->boolean('is_active')->default(true);
            $table->datetimes();
            $table->softDeletesDatetime();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
