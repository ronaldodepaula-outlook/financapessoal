<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unique(['id', 'user_id']);
            $table->string('original_filename', 255);
            $table->string('stored_path', 255);
            $table->string('file_hash', 64);
            $table->enum('format', ['CSV', 'OFX']);
            $table->foreignId('account_id')->nullable();
            $table->foreignId('card_id')->nullable();
            $table->json('column_mapping')->nullable();
            $table->enum('status', ['PREVIA', 'PROCESSANDO', 'CONCLUIDA', 'FALHOU'])->default('PREVIA');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'file_hash']);
            $table->foreign(['account_id', 'user_id'], 'imports_account_id_owner_fk')->references(['id', 'user_id'])->on('accounts')->restrictOnDelete();
            $table->foreign(['card_id', 'user_id'], 'imports_card_id_owner_fk')->references(['id', 'user_id'])->on('cards')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imports');
    }
};
