<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

class RecurrenceTest extends TestCase
{
    use AuthenticatesApi,RefreshDatabase;

    private function fixture(): array
    {
        $user = User::factory()->create();
        $headers = $this->apiHeaders($user);
        $account = $user->accounts()->create(['name' => 'Conta', 'account_type' => 'CARTEIRA']);
        $category = $user->categories()->create(['name' => 'Moradia', 'type' => 'DESPESA']);

        return [$user, $headers, ['category_id' => $category->id, 'account_id' => $account->id, 'amount' => '99.90', 'payment_method' => 'BOLETO', 'start_date' => '2027-01-01']];
    }

    public function test_fixed_expense_generates_once_clamps_day_and_preserves_history(): void
    {
        [$user,$headers,$data] = $this->fixture();
        $record = $this->postJson('/api/fixed-expenses', [...$data, 'description' => 'Internet', 'due_day' => 31], $headers)->assertCreated()->json('data');
        $this->assertSame(1, $user->transactions()->count());
        $this->postJson('/api/forecasts/generate', ['year' => 2027, 'month' => 1, 'months' => 3], $headers)->assertOk()->assertJsonPath('data.created', 2);
        $this->postJson('/api/forecasts/generate', ['year' => 2027, 'month' => 1, 'months' => 3], $headers)->assertJsonPath('data.created', 0);
        $this->assertSame('2027-02-28', $user->transactions()->where('competence_month', 2)->first()->due_date->toDateString());
        $this->putJson('/api/fixed-expenses/'.$record['id'], ['amount' => '120.00'], $headers)->assertOk();
        $this->assertSame('99.90', $user->transactions()->first()->amount);
        $this->putJson('/api/fixed-expenses/'.$record['id'], ['active' => false], $headers)->assertOk();
        $this->postJson('/api/forecasts/generate', ['year' => 2027, 'month' => 4], $headers)->assertJsonPath('data.created', 0);
    }

    public function test_subscription_cycles_keep_anchor_and_do_not_drift_in_february(): void
    {
        [$user,$headers,$data] = $this->fixture();
        $subscription = $this->postJson('/api/subscriptions', [...$data, 'name' => 'Software', 'billing_cycle' => 'MENSAL', 'next_due_date' => '2028-01-31'], $headers)->assertCreated()->json('data');
        $this->postJson('/api/forecasts/generate', ['year' => 2028, 'month' => 1, 'months' => 3], $headers)->assertOk();
        $dates = $user->transactions()->orderBy('transaction_date')->get()->map(fn ($r) => $r->due_date->toDateString())->all();
        $this->assertSame(['2028-01-31', '2028-02-29', '2028-03-31'], $dates);
        $this->getJson('/api/subscriptions/'.$subscription['id'], $headers)->assertJsonPath('data.next_due_date', '2028-04-30');
        $this->postJson('/api/subscriptions', [...$data, 'name' => 'Anual', 'billing_cycle' => 'ANUAL', 'next_due_date' => '2027-05-20'], $headers)->assertCreated();
        $this->postJson('/api/forecasts/generate', ['year' => 2027, 'month' => 1, 'months' => 12], $headers)->assertOk();
        $this->assertSame(1, $user->transactions()->where('description', 'Anual')->count());
    }

    public function test_cancelled_forecasts_are_not_recreated_and_archived_references_can_be_deactivated(): void
    {
        [$user, $headers, $data] = $this->fixture();
        $fixed = $this->postJson('/api/fixed-expenses', [...$data, 'description' => 'Internet', 'due_day' => 10], $headers)->assertCreated()->json('data.id');
        $tx = $user->transactions()->first();
        $this->putJson('/api/transactions/'.$tx->id, ['competence_month' => 2], $headers)->assertUnprocessable();
        $this->deleteJson('/api/transactions/'.$tx->id, [], $headers)->assertOk();
        $this->postJson('/api/forecasts/generate', ['year' => 2027, 'month' => 1], $headers)->assertJsonPath('data.created', 0);
        $this->artisan('finance:generate-forecasts', ['--from' => '2027-01', '--months' => 2, '--user' => $user->id])->assertSuccessful();
        $this->assertSame(2, $user->transactions()->count());
        $this->postJson('/api/forecasts/generate', ['year' => 2200, 'month' => 12, 'months' => 2], $headers)->assertUnprocessable();
        $this->assertSame(2, $user->transactions()->count());
        $this->deleteJson('/api/accounts/'.$data['account_id'], [], $headers)->assertOk();
        $this->postJson('/api/forecasts/generate', ['year' => 2027, 'month' => 3], $headers)->assertJsonPath('data.created', 0)->assertJsonCount(1, 'data.warnings');
        $this->putJson('/api/fixed-expenses/'.$fixed, ['active' => false], $headers)->assertOk();
    }

    public function test_dates_ownership_and_income_activation_are_validated(): void
    {
        [$user,$headers,$data] = $this->fixture();
        $this->postJson('/api/fixed-expenses', [...$data, 'description' => 'Inválida', 'due_day' => 32], $headers)->assertUnprocessable();
        $draft = $this->postJson('/api/income-schedules', ['description' => 'Salário', 'amount' => '3780.00', 'period' => '01_15'], $headers)->assertCreated()->json('data.id');
        $this->putJson('/api/income-schedules/'.$draft, ['active' => true], $headers)->assertUnprocessable();
        $this->putJson('/api/income-schedules/'.$draft, ['day' => 20], $headers)->assertUnprocessable();
        $this->getJson('/api/income-schedules/'.$draft, $this->apiHeaders(User::factory()->create()))->assertNotFound();
        $this->assertSame(0, $user->transactions()->count());
    }
}
