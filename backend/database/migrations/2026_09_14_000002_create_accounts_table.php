<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unique(['id', 'user_id']);
            $table->string('name', 120);
            $table->string('institution', 120)->nullable();
            $table->enum('account_type', ['CONTA_CORRENTE', 'POUPANCA', 'CARTEIRA', 'CONTA_DIGITAL', 'INVESTIMENTO']);
            $table->decimal('initial_balance', 15, 2)->default('0.00');
            $table->enum('status', ['ATIVO', 'INATIVO'])->default('ATIVO');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
