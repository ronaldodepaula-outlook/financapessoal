<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unique(['id', 'user_id']);
            $table->foreignId('loan_id');
            $table->foreignId('loan_installment_id')->nullable();
            $table->foreignId('account_id')->nullable();
            $table->enum('payment_type', ['PARCELA', 'AMORTIZACAO', 'QUITACAO']);
            $table->decimal('amount', 15, 2);
            $table->date('payment_date');
            $table->string('reference', 120)->nullable();
            $table->enum('status', ['PENDENTE', 'PAGA', 'CANCELADA'])->default('PENDENTE');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'loan_id', 'payment_date']);
            $table->foreign(['loan_id', 'user_id'], 'loan_payments_loan_id_owner_fk')->references(['id', 'user_id'])->on('loans')->restrictOnDelete();
            $table->foreign(['loan_installment_id', 'user_id'], 'loan_payments_loan_installment_id_owner_fk')->references(['id', 'user_id'])->on('loan_installments')->restrictOnDelete();
            $table->foreign(['account_id', 'user_id'], 'loan_payments_account_id_owner_fk')->references(['id', 'user_id'])->on('accounts')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_payments');
    }
};
