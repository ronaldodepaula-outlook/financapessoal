<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unique(['id', 'user_id']);
            $table->string('name', 160);
            $table->foreignId('category_id');
            $table->foreignId('subcategory_id')->nullable();
            $table->decimal('amount', 15, 2);
            $table->enum('billing_cycle', ['MENSAL', 'TRIMESTRAL', 'SEMESTRAL', 'ANUAL']);
            $table->date('next_due_date');
            $table->enum('payment_method', ['PIX', 'DINHEIRO', 'DEBITO', 'CREDITO', 'TRANSFERENCIA', 'BOLETO', 'OUTROS']);
            $table->foreignId('account_id')->nullable();
            $table->foreignId('card_id')->nullable();
            $table->boolean('active')->default(true);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign(['category_id', 'user_id'], 'subscriptions_category_id_owner_fk')->references(['id', 'user_id'])->on('categories')->restrictOnDelete();
            $table->foreign(['subcategory_id', 'user_id'], 'subscriptions_subcategory_id_owner_fk')->references(['id', 'user_id'])->on('categories')->restrictOnDelete();
            $table->foreign(['account_id', 'user_id'], 'subscriptions_account_id_owner_fk')->references(['id', 'user_id'])->on('accounts')->restrictOnDelete();
            $table->foreign(['card_id', 'user_id'], 'subscriptions_card_id_owner_fk')->references(['id', 'user_id'])->on('cards')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
