<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unique(['id', 'user_id']);
            $table->foreignId('account_id')->nullable();
            $table->foreignId('card_id')->nullable();
            $table->foreignId('category_id');
            $table->foreignId('subcategory_id')->nullable();
            $table->string('description', 255);
            $table->decimal('total_amount', 15, 2);
            $table->decimal('installment_amount', 15, 2);
            $table->unsignedSmallInteger('total_installments');
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['PENDENTE', 'PAGA', 'CANCELADA'])->default('PENDENTE');
            $table->timestamps();
            $table->foreign(['account_id', 'user_id'], 'installments_account_id_owner_fk')->references(['id', 'user_id'])->on('accounts')->restrictOnDelete();
            $table->foreign(['card_id', 'user_id'], 'installments_card_id_owner_fk')->references(['id', 'user_id'])->on('cards')->restrictOnDelete();
            $table->foreign(['category_id', 'user_id'], 'installments_category_id_owner_fk')->references(['id', 'user_id'])->on('categories')->restrictOnDelete();
            $table->foreign(['subcategory_id', 'user_id'], 'installments_subcategory_id_owner_fk')->references(['id', 'user_id'])->on('categories')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installments');
    }
};
