<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unique(['id', 'user_id']);
            $table->string('name', 160);
            $table->decimal('target_amount', 15, 2);
            $table->date('target_date')->nullable();
            $table->decimal('initial_amount', 15, 2)->default('0.00');
            $table->enum('status', ['ATIVA', 'CONCLUIDA', 'CANCELADA'])->default('ATIVA');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_goals');
    }
};
