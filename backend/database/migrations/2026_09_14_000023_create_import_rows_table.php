<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unique(['id', 'user_id']);
            $table->foreignId('import_id');
            $table->foreignId('transaction_id')->nullable();
            $table->unsignedInteger('row_number');
            $table->json('raw_data')->nullable();
            $table->json('mapped_data')->nullable();
            $table->string('fingerprint', 64)->nullable();
            $table->enum('status', ['PENDENTE', 'IMPORTADA', 'DUPLICADA', 'INVALIDA'])->default('PENDENTE');
            $table->json('validation_errors')->nullable();
            $table->timestamps();
            $table->unique(['import_id', 'row_number']);
            $table->index(['user_id', 'fingerprint']);
            $table->foreign(['import_id', 'user_id'], 'import_rows_import_id_owner_fk')->references(['id', 'user_id'])->on('imports')->restrictOnDelete();
            $table->foreign(['transaction_id', 'user_id'], 'import_rows_transaction_id_owner_fk')->references(['id', 'user_id'])->on('transactions')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_rows');
    }
};
