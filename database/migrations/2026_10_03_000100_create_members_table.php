<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete(); // nulo = sin cuenta de portal
            $table->string('member_number', 20)->unique(); // ID visible, p. ej. PL-000123
            $table->foreignId('home_location_id')->constrained('locations');
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('document_type', 10)->nullable();
            $table->string('document_number', 30)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('address_line', 255)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('department', 100)->nullable();
            $table->string('photo_path', 255)->nullable();
            $table->string('checkin_pin_hash', 255)->nullable(); // hash del PIN de 4 dígitos
            $table->string('status', 30);
            $table->date('joined_on');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->datetimes();
            $table->softDeletesDatetime();

            $table->unique(['document_type', 'document_number']);
            $table->index(['home_location_id', 'status']);
            $table->index('phone');
            $table->index('email');
            $table->index(['last_name', 'first_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
