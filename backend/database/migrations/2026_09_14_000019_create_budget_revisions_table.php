<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unique(['id', 'user_id']);
            $table->foreignId('budget_id');
            $table->unsignedInteger('version');
            $table->decimal('previous_amount', 15, 2);
            $table->decimal('new_amount', 15, 2);
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->unique(['budget_id', 'version']);
            $table->foreign(['budget_id', 'user_id'], 'budget_revisions_budget_id_owner_fk')->references(['id', 'user_id'])->on('budgets')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_revisions');
    }
};
