<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unique(['id', 'user_id']);
            $table->foreignId('source_account_id');
            $table->foreignId('destination_account_id');
            $table->decimal('amount', 15, 2);
            $table->date('transfer_date');
            $table->string('description', 255)->nullable();
            $table->enum('status', ['PENDENTE', 'PAGA', 'CANCELADA'])->default('PENDENTE');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'transfer_date']);
            $table->foreign(['source_account_id', 'user_id'], 'transfers_source_account_id_owner_fk')->references(['id', 'user_id'])->on('accounts')->restrictOnDelete();
            $table->foreign(['destination_account_id', 'user_id'], 'transfers_destination_account_id_owner_fk')->references(['id', 'user_id'])->on('accounts')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
