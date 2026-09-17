<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unique(['id', 'user_id']);
            $table->foreignId('card_id');
            $table->unsignedSmallInteger('competence_year');
            $table->unsignedTinyInteger('competence_month');
            $table->date('closing_date');
            $table->date('due_date');
            $table->enum('status', ['PENDENTE', 'PAGA', 'CANCELADA'])->default('PENDENTE');
            $table->timestamps();
            $table->unique(['card_id', 'competence_year', 'competence_month']);
            $table->foreign(['card_id', 'user_id'], 'card_invoices_card_id_owner_fk')->references(['id', 'user_id'])->on('cards')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_invoices');
    }
};
