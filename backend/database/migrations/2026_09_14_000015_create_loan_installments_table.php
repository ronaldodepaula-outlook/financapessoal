<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unique(['id', 'user_id']);
            $table->foreignId('loan_id');
            $table->foreignId('transaction_id')->nullable();
            $table->unsignedSmallInteger('number');
            $table->date('due_date');
            $table->decimal('amount', 15, 2);
            $table->decimal('principal_component', 15, 2)->nullable();
            $table->decimal('interest_component', 15, 2)->nullable();
            $table->enum('status', ['PENDENTE', 'PAGA', 'CANCELADA'])->default('PENDENTE');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['loan_id', 'number']);
            $table->unique('transaction_id');
            $table->index(['user_id', 'status', 'due_date']);
            $table->foreign(['loan_id', 'user_id'], 'loan_installments_loan_id_owner_fk')->references(['id', 'user_id'])->on('loans')->restrictOnDelete();
            $table->foreign(['transaction_id', 'user_id'], 'loan_installments_transaction_id_owner_fk')->references(['id', 'user_id'])->on('transactions')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_installments');
    }
};
