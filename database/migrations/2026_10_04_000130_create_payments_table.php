<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // `payment_method_id` (FK payment_methods) se añade en la Fase 10 junto con su tabla.
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices');
            $table->foreignId('member_id')->constrained('members');
            $table->foreignId('location_id')->constrained('locations');
            $table->bigInteger('amount_cents');
            $table->char('currency', 3);
            $table->string('method', 30); // cash | card_terminal | transfer | bre_b | nequi | pse | gateway
            $table->string('status', 30); // pending | paid | failed | refunded | cancelled
            $table->string('gateway', 30)->nullable();
            $table->string('external_transaction_id', 120)->nullable();
            $table->string('reference', 100)->nullable(); // n.º de comprobante o voucher
            $table->dateTime('paid_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users');
            $table->json('meta')->nullable(); // respuesta de la pasarela
            $table->datetimes();

            $table->unique(['gateway', 'external_transaction_id']);
            $table->index(['member_id', 'paid_at']);
            $table->index(['location_id', 'paid_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
