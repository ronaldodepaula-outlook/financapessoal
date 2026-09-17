<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_balance_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unique(['id', 'user_id']);
            $table->foreignId('loan_id');
            $table->decimal('outstanding_balance', 15, 2);
            $table->date('reported_at');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'loan_id', 'reported_at']);
            $table->foreign(['loan_id', 'user_id'], 'loan_balance_snapshots_loan_id_owner_fk')->references(['id', 'user_id'])->on('loans')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_balance_snapshots');
    }
};
