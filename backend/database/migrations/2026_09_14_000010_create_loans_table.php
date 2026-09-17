<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unique(['id', 'user_id']);
            $table->string('name', 160);
            $table->string('institution', 120)->nullable();
            $table->enum('loan_type', ['PESSOAL', 'CONSIGNADO', 'FINANCIAMENTO', 'OUTROS']);
            $table->decimal('principal_amount', 15, 2);
            $table->decimal('interest_rate', 8, 4)->nullable();
            $table->decimal('cet', 8, 4)->nullable();
            $table->decimal('annual_cet', 8, 4)->nullable();
            $table->decimal('iof', 15, 2)->nullable();
            $table->unsignedSmallInteger('installments');
            $table->decimal('installment_amount', 15, 2);
            $table->date('start_date')->nullable();
            $table->date('first_due_date')->nullable();
            $table->date('end_date')->nullable();
            $table->enum('status', ['RASCUNHO', 'ATIVO', 'QUITADO', 'CANCELADO'])->default('RASCUNHO');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
