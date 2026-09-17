<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unique(['id', 'user_id']);
            $table->foreignId('card_invoice_id');
            $table->foreignId('account_id');
            $table->decimal('amount', 15, 2);
            $table->date('payment_date');
            $table->enum('status', ['PENDENTE', 'PAGA', 'CANCELADA'])->default('PENDENTE');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'payment_date']);
            $table->foreign(['card_invoice_id', 'user_id'], 'card_invoice_payments_card_invoice_id_owner_fk')->references(['id', 'user_id'])->on('card_invoices')->restrictOnDelete();
            $table->foreign(['account_id', 'user_id'], 'card_invoice_payments_account_id_owner_fk')->references(['id', 'user_id'])->on('accounts')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_invoice_payments');
    }
};
