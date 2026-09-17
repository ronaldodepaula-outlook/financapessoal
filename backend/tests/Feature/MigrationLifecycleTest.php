<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrationLifecycleTest extends TestCase
{
    public function test_migrations_can_rollback_and_run_again_without_foreign_key_errors(): void
    {
        // O guard de TestCase impede execução fora de SQLite em memória.
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->assertTrue(Schema::hasTable('transactions'));
        $this->artisan('migrate:rollback', ['--force' => true])->assertSuccessful();
        $this->assertFalse(Schema::hasTable('transactions'));
        $this->assertFalse(Schema::hasTable('users'));
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->assertTrue(Schema::hasTable('transactions'));
        $this->assertTrue(Schema::hasTable('audit_logs'));
    }
}
