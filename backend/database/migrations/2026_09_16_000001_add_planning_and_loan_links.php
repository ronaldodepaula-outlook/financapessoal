<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->date('billing_anchor_date')->nullable();
        });
        Schema::table('income_schedules', function (Blueprint $table) {
            $table->string('template_key', 80)->nullable();
            $table->unique(['user_id', 'template_key']);
        });
        Schema::table('loans', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable();
            $table->foreignId('subcategory_id')->nullable();
            $table->foreignId('account_id')->nullable();
            $table->enum('cash_flow_mode', ['CONTA', 'FOLHA'])->nullable();
            $table->string('template_key', 80)->nullable();
            $table->unique(['user_id', 'template_key']);
            foreach (['category_id' => 'categories', 'subcategory_id' => 'categories', 'account_id' => 'accounts'] as $field => $target) {
                $table->foreign([$field, 'user_id'], 'loans_'.$field.'_owner_fk')->references(['id', 'user_id'])->on($target)->restrictOnDelete();
            }
        });
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('loan_id')->nullable();
            $table->boolean('cash_flow_effect')->default(true);
            $table->foreign(['loan_id', 'user_id'], 'transactions_loan_owner_fk')->references(['id', 'user_id'])->on('loans')->restrictOnDelete();
        });
        Schema::table('loan_payments', function (Blueprint $table) {
            $table->foreignId('transaction_id')->nullable();
            $table->foreign(['transaction_id', 'user_id'], 'loan_payments_transaction_owner_fk')->references(['id', 'user_id'])->on('transactions')->restrictOnDelete();
        });
        Schema::table('loan_installments', function (Blueprint $table) {
            $table->foreignId('settled_by_payment_id')->nullable();
            $table->foreign(['settled_by_payment_id', 'user_id'], 'loan_installments_settlement_owner_fk')->references(['id', 'user_id'])->on('loan_payments')->restrictOnDelete();
        });
        Schema::table('goal_contributions', function (Blueprint $table) {
            $table->dateTime('cancelled_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('goal_contributions', fn (Blueprint $t) => $t->dropColumn('cancelled_at'));
        Schema::table('loan_installments', function (Blueprint $t) {
            $t->dropForeign('loan_installments_settlement_owner_fk')->columns(['settled_by_payment_id', 'user_id']);
            $t->dropColumn('settled_by_payment_id');
        });
        Schema::table('loan_payments', function (Blueprint $t) {
            $t->dropForeign('loan_payments_transaction_owner_fk')->columns(['transaction_id', 'user_id']);
            $t->dropColumn('transaction_id');
        });
        Schema::table('transactions', function (Blueprint $t) {
            $t->dropForeign('transactions_loan_owner_fk')->columns(['loan_id', 'user_id']);
            $t->dropColumn(['loan_id', 'cash_flow_effect']);
        });
        Schema::table('loans', function (Blueprint $t) {
            foreach (['category_id', 'subcategory_id', 'account_id'] as $field) {
                $t->dropForeign('loans_'.$field.'_owner_fk')->columns([$field, 'user_id']);
            }
            $t->dropUnique(['user_id', 'template_key']);
            $t->dropColumn(['category_id', 'subcategory_id', 'account_id', 'cash_flow_mode', 'template_key']);
        });
        Schema::table('income_schedules', function (Blueprint $t) {
            $t->dropUnique(['user_id', 'template_key']);
            $t->dropColumn('template_key');
        });
        Schema::table('subscriptions', fn (Blueprint $t) => $t->dropColumn('billing_anchor_date'));
    }
};
