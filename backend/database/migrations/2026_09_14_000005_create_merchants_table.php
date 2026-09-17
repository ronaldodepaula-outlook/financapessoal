<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unique(['id', 'user_id']);
            $table->string('name', 160);
            $table->string('normalized_name', 160);
            $table->foreignId('category_id')->nullable();
            $table->foreignId('subcategory_id')->nullable();
            $table->enum('status', ['ATIVO', 'INATIVO'])->default('ATIVO');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['user_id', 'normalized_name']);
            $table->foreign(['category_id', 'user_id'], 'merchants_category_id_owner_fk')->references(['id', 'user_id'])->on('categories')->restrictOnDelete();
            $table->foreign(['subcategory_id', 'user_id'], 'merchants_subcategory_id_owner_fk')->references(['id', 'user_id'])->on('categories')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchants');
    }
};
