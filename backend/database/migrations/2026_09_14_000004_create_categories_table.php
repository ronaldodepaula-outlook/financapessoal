<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unique(['id', 'user_id']);
            $table->string('name', 120);
            $table->enum('type', ['RECEITA', 'DESPESA']);
            $table->foreignId('parent_id')->nullable();
            $table->unsignedBigInteger('parent_scope')->storedAs('COALESCE(parent_id, 0)');
            $table->enum('status', ['ATIVO', 'INATIVO'])->default('ATIVO');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['user_id', 'type', 'parent_scope', 'name'], 'categories_identity_unique');
            $table->foreign(['parent_id', 'user_id'], 'categories_parent_id_owner_fk')->references(['id', 'user_id'])->on('categories')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
