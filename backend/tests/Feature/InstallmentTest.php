<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

class InstallmentTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    private function fixture(): array
    {
        $user = User::factory()->create();
        $headers = $this->apiHeaders($user);
        $account = $user->accounts()->create(['name' => 'Carteira', 'account_type' => 'CARTEIRA', 'initial_balance' => '1000.00']);
        $category = $user->categories()->create(['name' => 'Compras', 'type' => 'DESPESA']);

        return [$user, $headers, $account, ['description' => 'Notebook', 'total_amount' => '100.00', 'total_installments' => 3, 'start_date' => '2028-01-31', 'category_id' => $category->id, 'account_id' => $account->id, 'payment_method' => 'BOLETO']];
    }

    public function test_schedule_preserves_total_and_original_day_across_short_months(): void
    {
        [$user,$headers,$account,$data] = $this->fixture();
        $purchase = $this->postJson('/api/installments', $data, $headers)->assertCreated()->json('data');
        $this->assertSame(['33.34', '33.33', '33.33'], array_column($purchase['schedule'], 'amount'));
        $this->assertSame(['2028-01-31', '2028-02-29', '2028-03-31'], array_map(fn ($date) => substr($date, 0, 10), array_column($purchase['schedule'], 'due_date')));
        $this->assertSame([1, 2, 3], array_column($purchase['schedule'], 'installment_number'));
        $this->assertSame('100.00', $purchase['outstanding_balance']);
        $this->assertSame(3, $user->transactions()->count());
        $this->getJson('/api/accounts/'.$account->id, $headers)->assertJsonPath('data.current_balance', '1000.00');
        $this->getJson('/api/installments/'.$purchase['id'], $this->apiHeaders(User::factory()->create()))->assertNotFound();
    }

    public function test_payment_reduces_outstanding_and_cancellation_preserves_paid_installments(): void
    {
        [$user,$headers,$account,$data] = $this->fixture();
        $purchase = $this->postJson('/api/installments', $data, $headers)->assertCreated()->json('data');
        $first = $purchase['schedule'][0]['id'];
        $this->putJson('/api/transactions/'.$first, ['status' => 'PAGA'], $headers)->assertOk();
        $this->getJson('/api/installments/'.$purchase['id'], $headers)->assertJsonPath('data.paid_installments', 1)->assertJsonPath('data.outstanding_balance', '66.66');
        $this->putJson('/api/transactions/'.$first, ['amount' => '1.00'], $headers)->assertUnprocessable();
        $this->deleteJson('/api/installments/'.$purchase['id'], [], $headers)->assertOk();
        $this->getJson('/api/installments/'.$purchase['id'], $headers)->assertJsonPath('data.paid_installments', 1)->assertJsonPath('data.remaining_installments', 0);
        $this->getJson('/api/accounts/'.$account->id, $headers)->assertJsonPath('data.current_balance', '966.66');
    }

    public function test_invalid_schedule_leaves_no_partial_purchase_or_transactions(): void
    {
        [$user,$headers,$account,$data] = $this->fixture();
        $this->postJson('/api/installments', [...$data, 'total_amount' => '0.01'], $headers)->assertUnprocessable();
        $this->postJson('/api/installments', [...$data, 'total_installments' => 601], $headers)->assertUnprocessable();
        $this->assertSame(0, $user->installments()->count());
        $this->assertSame(0, $user->transactions()->count());
    }

    public function test_installment_purchase_becomes_paid_when_all_installments_are_paid(): void
    {
        [$user,$headers,$account,$data] = $this->fixture();
        $purchase = $this->postJson('/api/installments', $data, $headers)->assertCreated()->json('data');
        foreach ($purchase['schedule'] as $row) {
            $this->putJson('/api/transactions/'.$row['id'], ['status' => 'PAGA'], $headers)->assertOk();
        }
        $this->getJson('/api/installments/'.$purchase['id'],$headers)->assertJsonPath('data.status','PAGA')->assertJsonPath('data.outstanding_balance','0.00');
    }
}
