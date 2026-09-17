<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goal_contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unique(['id', 'user_id']);
            $table->foreignId('financial_goal_id');
            $table->foreignId('transfer_id')->nullable();
            $table->decimal('amount', 15, 2);
            $table->date('contribution_date');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'financial_goal_id', 'contribution_date'], 'goal_contributions_date_index');
            $table->foreign(['financial_goal_id', 'user_id'], 'goal_contributions_financial_goal_id_owner_fk')->references(['id', 'user_id'])->on('financial_goals')->restrictOnDelete();
            $table->foreign(['transfer_id', 'user_id'], 'goal_contributions_transfer_id_owner_fk')->references(['id', 'user_id'])->on('transfers')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goal_contributions');
    }
};
