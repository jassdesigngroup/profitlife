<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->nullable()->unique(); // se asigna al emitir
            $table->foreignId('member_id')->constrained('members');
            $table->foreignId('location_id')->constrained('locations');
            $table->string('status', 30); // draft | issued | partially_paid | paid | void
            $table->dateTime('issued_at')->nullable();
            $table->date('due_on')->nullable();
            $table->bigInteger('subtotal_cents');
            $table->bigInteger('discount_cents');
            $table->bigInteger('tax_cents');
            $table->bigInteger('total_cents');
            $table->bigInteger('paid_cents');
            $table->char('currency', 3);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->datetimes();
            $table->softDeletesDatetime();

            $table->index(['member_id', 'status']);
            $table->index(['location_id', 'issued_at']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('billable_type', 100)->nullable(); // membership | appointment | service ...
            $table->unsignedBigInteger('billable_id')->nullable();
            $table->string('description', 255);
            $table->smallInteger('quantity')->default(1);
            $table->bigInteger('unit_price_cents');
            $table->bigInteger('discount_cents')->default(0);
            $table->smallInteger('tax_rate_bps')->default(0);
            $table->bigInteger('tax_cents');
            $table->bigInteger('total_cents');

            $table->index(['billable_type', 'billable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
