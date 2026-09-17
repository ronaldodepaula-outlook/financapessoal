<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\User;
use App\Services\InitialPlanningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

class PlanningTest extends TestCase
{
    use AuthenticatesApi,RefreshDatabase;

    public function test_initialization_is_idempotent_and_keeps_unknown_parameters_inactive(): void
    {
        $user = User::factory()->create();
        $headers = $this->apiHeaders($user);
        $this->postJson('/api/planning/initialize', [], $headers)->assertOk()->assertJsonPath('data.created.budgets', 168)->assertJsonPath('data.created.loans', 1);
        $this->assertSame(2, $user->incomeSchedules()->where('active', false)->whereNull('day')->count());
        $this->assertNull($user->loans()->first()->first_due_date);
        $this->assertSame(0, $user->transactions()->count());
        $budget = $user->budgets()->first();
        $this->putJson('/api/budgets/'.$budget->id, ['planned_amount' => '123.45', 'reason' => 'Ajuste pessoal'], $headers)->assertOk();
        $income = $user->incomeSchedules()->first();
        $this->putJson('/api/income-schedules/'.$income->id, ['description' => 'Minha renda'], $headers)->assertOk();
        $this->putJson('/api/categories/'.$budget->category_id, ['name' => 'Minha alimentação'], $headers)->assertOk();
        $categoryCount = $user->categories()->count();
        $this->putJson('/api/preferences', ['income_is_net_of_payroll_loan' => true], $headers)->assertOk();
        $this->postJson('/api/planning/initialize', [], $headers)->assertJsonPath('data.created.budgets', 0)->assertJsonPath('data.created.income_schedules', 0)->assertJsonPath('data.created.loans', 0);
        $this->assertSame('123.45', $budget->fresh()->planned_amount);
        $this->assertSame(168, $user->budgets()->count());
        $this->assertSame($categoryCount, $user->categories()->count());
        $this->getJson('/api/budgets/'.$budget->id, $headers)->assertJsonCount(2, 'data.revisions')->assertJsonPath('data.revisions.1.previous_amount', '2400.00');
    }

    public function test_budget_copy_preserves_source_and_requires_explicit_overwrite(): void
    {
        $user = User::factory()->create();
        $headers = $this->apiHeaders($user);
        $category = $user->categories()->create(['name' => 'Saúde', 'type' => 'DESPESA']);
        $data = ['year' => 2027, 'month' => 2, 'category_id' => $category->id, 'planned_amount' => '250.00'];
        $source = $this->postJson('/api/budgets', $data, $headers)->assertCreated()->json('data.id');
        $this->postJson('/api/budgets/copy', ['source_year' => 2027, 'target_year' => 2028], $headers)->assertJsonPath('data.created', 1);
        $target = $user->budgets()->where('year', 2028)->first();
        $this->putJson('/api/budgets/'.$target->id, ['planned_amount' => '300.00'], $headers)->assertOk();
        $this->postJson('/api/budgets/copy', ['source_year' => 2027, 'target_year' => 2028], $headers)->assertJsonPath('data.skipped', 1);
        $this->assertSame('300.00', $target->fresh()->planned_amount);
        $this->postJson('/api/budgets/copy', ['source_year' => 2027, 'target_year' => 2028, 'overwrite' => true], $headers)->assertJsonPath('data.updated', 1);
        $this->assertSame('250.00', $target->fresh()->planned_amount);
        $this->assertSame(1, Budget::find($source)->revisions()->count());
        $this->postJson('/api/budgets', [...$data, 'year' => 2020], $headers)->assertUnprocessable();
        $child = $user->categories()->create(['name' => 'Farmácia', 'type' => 'DESPESA', 'parent_id' => $category->id]);
        $this->postJson('/api/budgets', [...$data, 'subcategory_id' => $child->id], $headers)->assertUnprocessable();
        $this->getJson('/api/budgets/'.$source, $this->apiHeaders(User::factory()->create()))->assertNotFound();
    }

    public function test_monthly_comparison_and_fortnight_count_each_expense_once(): void
    {
        $user = User::factory()->create();
        $headers = $this->apiHeaders($user);
        $account = $user->accounts()->create(['name' => 'Conta', 'account_type' => 'CARTEIRA']);
        $expense = $user->categories()->create(['name' => 'Compras', 'type' => 'DESPESA']);
        $income = $user->categories()->create(['name' => 'Salário', 'type' => 'RECEITA']);
        $this->postJson('/api/budgets', ['year' => 2028, 'month' => 2, 'category_id' => $expense->id, 'planned_amount' => '200.00'], $headers)->assertCreated();
        foreach ([['01_15', 15, '3780.00'], ['16_31', 31, '2700.00']] as [$period,$day,$amount]) {
            $this->postJson('/api/income-schedules', ['description' => 'Renda', 'amount' => $amount, 'period' => $period, 'day' => $day, 'start_date' => '2028-02-01', 'active' => true, 'category_id' => $income->id, 'account_id' => $account->id], $headers)->assertCreated();
        }
        $base = ['description' => 'Compra', 'transaction_type' => 'DESPESA', 'category_id' => $expense->id, 'account_id' => $account->id, 'payment_method' => 'PIX', 'competence_year' => 2028, 'competence_month' => 2];
        $this->postJson('/api/transactions', [...$base, 'amount' => '120.10', 'transaction_date' => '2028-02-15', 'status' => 'PAGA'], $headers)->assertCreated();
        $this->postJson('/api/transactions', [...$base, 'amount' => '40.00', 'transaction_date' => '2028-02-29', 'status' => 'PENDENTE'], $headers)->assertCreated();
        $this->postJson('/api/transactions', [...$base, 'amount' => '900.00', 'transaction_date' => '2028-02-29', 'status' => 'CANCELADA'], $headers)->assertCreated();
        $this->getJson('/api/budgets/comparison?year=2028&month=2', $headers)->assertJsonPath('data.actual_amount', '120.10')->assertJsonPath('data.committed_amount', '160.10')->assertJsonPath('data.items.0.usage_percent', '60.05');
        $this->getJson('/api/budgets/fortnight?year=2028&month=2', $headers)->assertJsonPath('data.periods.0.available_to_spend', '3659.90')->assertJsonPath('data.periods.1.available_to_spend', '2660.00')->assertJsonPath('data.periods.1.to', '2028-02-29');
        $this->postJson('/api/forecasts/generate', ['year' => 2028, 'month' => 2], $headers)->assertJsonPath('data.created', 0);
    }

    public function test_payroll_forecasts_do_not_reduce_net_income_twice(): void
    {
        $user = User::factory()->create();
        $headers = $this->apiHeaders($user);
        app(InitialPlanningService::class)->initialize($user);
        $this->putJson('/api/preferences', ['income_is_net_of_payroll_loan' => true], $headers)->assertOk();
        $loan = $user->loans()->first();
        $this->postJson('/api/loans/'.$loan->id.'/activate', ['first_due_date' => '2027-01-10', 'cash_flow_mode' => 'FOLHA'], $headers)->assertOk();
        $this->getJson('/api/budgets/fortnight?year=2027&month=1', $headers)->assertJsonPath('data.periods.0.committed_expenses', '0.00')->assertJsonPath('data.periods.0.payroll_already_deducted', '1024.77');
        $this->putJson('/api/preferences', ['income_is_net_of_payroll_loan' => false], $headers)->assertUnprocessable();
        $this->postJson('/api/transactions', ['loan_id' => $loan->id, 'cash_flow_effect' => false], $headers)->assertUnprocessable();
    }
}
