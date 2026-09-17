<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unique(['id', 'user_id']);
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->foreignId('category_id');
            $table->foreignId('subcategory_id')->nullable();
            $table->unsignedBigInteger('subcategory_scope')->storedAs('COALESCE(subcategory_id, 0)');
            $table->decimal('planned_amount', 15, 2);
            $table->timestamps();
            $table->unique(['user_id', 'year', 'month', 'category_id', 'subcategory_scope'], 'budgets_period_category_unique');
            $table->foreign(['category_id', 'user_id'], 'budgets_category_id_owner_fk')->references(['id', 'user_id'])->on('categories')->restrictOnDelete();
            $table->foreign(['subcategory_id', 'user_id'], 'budgets_subcategory_id_owner_fk')->references(['id', 'user_id'])->on('categories')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
