<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('income_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unique(['id', 'user_id']);
            $table->string('description', 255);
            $table->decimal('amount', 15, 2);
            $table->unsignedTinyInteger('day')->nullable();
            $table->enum('period', ['01_15', '16_31']);
            $table->foreignId('account_id')->nullable();
            $table->foreignId('category_id')->nullable();
            $table->boolean('active')->default(false);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign(['account_id', 'user_id'], 'income_schedules_account_id_owner_fk')->references(['id', 'user_id'])->on('accounts')->restrictOnDelete();
            $table->foreign(['category_id', 'user_id'], 'income_schedules_category_id_owner_fk')->references(['id', 'user_id'])->on('categories')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('income_schedules');
    }
};
