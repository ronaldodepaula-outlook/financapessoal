<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unique(['id', 'user_id']);
            $table->foreignId('account_id')->nullable();
            $table->foreignId('card_id')->nullable();
            $table->foreignId('category_id');
            $table->foreignId('subcategory_id')->nullable();
            $table->foreignId('merchant_id')->nullable();
            $table->foreignId('card_invoice_id')->nullable();
            $table->enum('transaction_type', ['RECEITA', 'DESPESA']);
            $table->string('description', 255);
            $table->date('transaction_date');
            $table->date('due_date')->nullable();
            $table->unsignedTinyInteger('competence_month');
            $table->unsignedSmallInteger('competence_year');
            $table->decimal('amount', 15, 2);
            $table->enum('payment_method', ['PIX', 'DINHEIRO', 'DEBITO', 'CREDITO', 'TRANSFERENCIA', 'BOLETO', 'OUTROS']);
            $table->boolean('is_fixed')->default(false);
            $table->boolean('is_installment')->default(false);
            $table->foreignId('installment_id')->nullable();
            $table->unsignedSmallInteger('installment_number')->nullable();
            $table->foreignId('fixed_expense_id')->nullable();
            $table->foreignId('subscription_id')->nullable();
            $table->foreignId('income_schedule_id')->nullable();
            $table->enum('status', ['PENDENTE', 'PAGA', 'CANCELADA'])->default('PENDENTE');
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->string('import_fingerprint', 64)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'transaction_date']);
            $table->index(['user_id', 'competence_year', 'competence_month', 'transaction_type'], 'transactions_competence_index');
            $table->index(['user_id', 'status', 'due_date']);
            $table->unique(['installment_id', 'installment_number']);
            $table->unique(['fixed_expense_id', 'competence_year', 'competence_month'], 'transactions_fixed_period_unique');
            $table->unique(['subscription_id', 'competence_year', 'competence_month'], 'transactions_subscription_period_unique');
            $table->unique(['income_schedule_id', 'competence_year', 'competence_month'], 'transactions_income_period_unique');
            $table->unique(['user_id', 'import_fingerprint']);
            $table->foreign(['account_id', 'user_id'], 'transactions_account_id_owner_fk')->references(['id', 'user_id'])->on('accounts')->restrictOnDelete();
            $table->foreign(['card_id', 'user_id'], 'transactions_card_id_owner_fk')->references(['id', 'user_id'])->on('cards')->restrictOnDelete();
            $table->foreign(['category_id', 'user_id'], 'transactions_category_id_owner_fk')->references(['id', 'user_id'])->on('categories')->restrictOnDelete();
            $table->foreign(['subcategory_id', 'user_id'], 'transactions_subcategory_id_owner_fk')->references(['id', 'user_id'])->on('categories')->restrictOnDelete();
            $table->foreign(['merchant_id', 'user_id'], 'transactions_merchant_id_owner_fk')->references(['id', 'user_id'])->on('merchants')->restrictOnDelete();
            $table->foreign(['card_invoice_id', 'user_id'], 'transactions_card_invoice_id_owner_fk')->references(['id', 'user_id'])->on('card_invoices')->restrictOnDelete();
            $table->foreign(['installment_id', 'user_id'], 'transactions_installment_id_owner_fk')->references(['id', 'user_id'])->on('installments')->restrictOnDelete();
            $table->foreign(['fixed_expense_id', 'user_id'], 'transactions_fixed_expense_id_owner_fk')->references(['id', 'user_id'])->on('fixed_expenses')->restrictOnDelete();
            $table->foreign(['subscription_id', 'user_id'], 'transactions_subscription_id_owner_fk')->references(['id', 'user_id'])->on('subscriptions')->restrictOnDelete();
            $table->foreign(['income_schedule_id', 'user_id'], 'transactions_income_schedule_id_owner_fk')->references(['id', 'user_id'])->on('income_schedules')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
