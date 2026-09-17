<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\InitialCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    public function test_catalog_crud_is_authenticated_scoped_paginated_and_audited(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $headers = $this->apiHeaders($first);
        $other = $this->apiHeaders($second);
        $this->getJson('/api/accounts')->assertUnauthorized();
        $account = $this->postJson('/api/accounts', ['name' => 'Principal', 'account_type' => 'CONTA_CORRENTE', 'initial_balance' => '100.10'], $headers)->assertCreated()->json('data');
        $this->getJson('/api/accounts?per_page=1', $headers)->assertOk()->assertJsonPath('data.pagination.total', 1);
        $this->getJson('/api/accounts', $other)->assertOk()->assertJsonPath('data.pagination.total', 0);
        $this->getJson('/api/accounts/'.$account['id'], $other)->assertNotFound();
        $this->putJson('/api/accounts/'.$account['id'], ['name' => 'Invadida'], $other)->assertNotFound();
        $this->putJson('/api/accounts/'.$account['id'], ['name' => 'Conta principal'], $headers)->assertOk()->assertJsonPath('data.name', 'Conta principal');
        $this->deleteJson('/api/accounts/'.$account['id'], [], $headers)->assertOk();
        $this->getJson('/api/accounts/'.$account['id'], $headers)->assertNotFound();
        $this->assertSame(3, $first->auditLogs()->where('entity_type', 'Account')->count());
    }

    public function test_values_and_owner_injection_are_rejected(): void
    {
        $headers = $this->apiHeaders(User::factory()->create());
        $this->postJson('/api/accounts', ['name' => 'Conta', 'account_type' => 'CARTEIRA', 'user_id' => 999], $headers)->assertUnprocessable()->assertJsonValidationErrors('user_id');
        $this->postJson('/api/cards', ['name' => 'Cartão', 'credit_limit' => '10.999', 'closing_day' => 32, 'due_day' => 0], $headers)->assertUnprocessable()->assertJsonValidationErrors(['credit_limit', 'closing_day', 'due_day']);
        $this->postJson('/api/cards', ['name' => 'Cartão', 'credit_limit' => '1000.00', 'closing_day' => 20, 'due_day' => 5, 'last_digits' => '1234'], $headers)->assertCreated();
    }

    public function test_category_hierarchy_and_uniqueness_are_enforced(): void
    {
        $headers = $this->apiHeaders(User::factory()->create());
        $root = $this->postJson('/api/categories', ['name' => 'Alimentação', 'type' => 'DESPESA'], $headers)->assertCreated()->json('data.id');
        $child = $this->postJson('/api/categories', ['name' => 'Mercado', 'type' => 'DESPESA', 'parent_id' => $root], $headers)->assertCreated()->json('data.id');
        $this->postJson('/api/categories', ['name' => 'Terceiro nível', 'type' => 'DESPESA', 'parent_id' => $child], $headers)->assertUnprocessable();
        $this->postJson('/api/categories', ['name' => 'Salário', 'type' => 'RECEITA', 'parent_id' => $root], $headers)->assertUnprocessable();
        $this->postJson('/api/categories', ['name' => 'Alimentação', 'type' => 'DESPESA'], $headers)->assertUnprocessable();
        $this->putJson('/api/categories/'.$root, ['parent_id' => $child], $headers)->assertUnprocessable();
        $this->putJson('/api/categories/'.$root, ['status' => 'INATIVO'], $headers)->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->deleteJson('/api/categories/'.$root, [], $headers)->assertConflict();
        $this->deleteJson('/api/categories/'.$child, [], $headers)->assertOk();
        $this->putJson('/api/categories/'.$root, ['status' => 'INATIVO'], $headers)->assertOk()->assertJsonPath('data.status', 'INATIVO');
    }

    public function test_merchant_normalization_does_not_merge_distinct_names_heuristically(): void
    {
        $headers = $this->apiHeaders(User::factory()->create());
        $this->postJson('/api/merchants', ['name' => '  Mercado   Cometa  '], $headers)->assertCreated()->assertJsonPath('data.normalized_name', 'MERCADO COMETA');
        $this->postJson('/api/merchants', ['name' => 'mercado cometa'], $headers)->assertUnprocessable();
        $this->postJson('/api/merchants', ['name' => 'Cometa'], $headers)->assertCreated();
    }

    public function test_initial_categories_are_idempotent_and_keep_financial_settings_unconfirmed(): void
    {
        $user = User::factory()->create();
        $service = app(InitialCatalogService::class);
        $service->initialize($user);
        $count = $user->categories()->count();
        $service->initialize($user);
        $this->assertSame($count, $user->categories()->count());
        $this->assertGreaterThan(20,$count);
        $this->assertNull($user->userPreferences->income_is_net_of_payroll_loan);
        $this->assertSame(0,$user->transactions()->count());
    }
}
