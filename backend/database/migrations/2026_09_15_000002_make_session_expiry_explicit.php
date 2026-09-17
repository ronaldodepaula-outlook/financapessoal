<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MariaDB com explicit_defaults_for_timestamp=OFF altera automaticamente
        // o primeiro TIMESTAMP. Expiração deve mudar somente pela aplicação.
        Schema::table('auth_sessions', function (Blueprint $table) {
            $table->dateTime('expires_at')->change();
        });
    }

    public function down(): void
    {
        Schema::table('auth_sessions', function (Blueprint $table) {
            $table->timestamp('expires_at')->change();
        });
    }
};
