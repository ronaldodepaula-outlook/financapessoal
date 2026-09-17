<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

class LoanTest extends TestCase
{
    use AuthenticatesApi,RefreshDatabase;

    private function fixture(): array
    {
        $user = User::factory()->create();
        $headers = $this->apiHeaders($user);
        $account = $user->accounts()->create(['name' => 'Conta', 'account_type' => 'CARTEIRA', 'initial_balance' => '1000.00']);
        $category = $user->categories()->create(['name' => 'Financeiro', 'type' => 'DESPESA']);
        $data = ['name' => 'Empréstimo', 'loan_type' => 'PESSOAL', 'principal_amount' => '250.00', 'installment_amount' => '100.00', 'installments' => 3, 'first_due_date' => '2027-01-31', 'category_id' => $category->id, 'account_id' => $account->id, 'cash_flow_mode' => 'CONTA'];
        $id = $this->postJson('/api/loans', $data, $headers)->assertCreated()->assertJsonPath('data.outstanding_balance', null)->json('data.id');

        return [$user, $headers, $account, $id];
    }

    public function test_activation_payment_and_reversal_do_not_duplicate_expenses(): void
    {
        [$user,$headers,$account,$id] = $this->fixture();
        $loan = $this->postJson("/api/loans/$id/activate", [], $headers)->assertOk()->assertJsonPath('data.scheduled_payables', '300.00')->json('data');
        $this->assertSame(['2027-01-31', '2027-02-28', '2027-03-31'], array_column($loan['schedule'], 'due_date'));
        $this->postJson("/api/loans/$id/activate", [], $headers)->assertOk();
        $this->assertSame(3, $user->transactions()->count());
        $payment = $this->postJson("/api/loans/$id/payments", ['payment_type' => 'PARCELA', 'loan_installment_id' => $loan['schedule'][0]['id'], 'amount' => '100.00', 'payment_date' => '2027-01-31'], $headers)->assertCreated()->json('data.id');
        $this->assertSame(3, $user->transactions()->count());
        $this->getJson('/api/accounts/'.$account->id, $headers)->assertJsonPath('data.current_balance', '900.00');
        $this->putJson('/api/transactions/'.$loan['schedule'][0]['transaction_id'], ['status' => 'CANCELADA'], $headers)->assertUnprocessable();
        $this->postJson("/api/loans/$id/payments", ['payment_type' => 'PARCELA', 'loan_installment_id' => $loan['schedule'][0]['id'], 'amount' => '100.00', 'payment_date' => '2027-01-31'], $headers)->assertUnprocessable();
        $this->deleteJson('/api/loan-payments/'.$payment, [], $headers)->assertOk();
        $this->getJson('/api/accounts/'.$account->id, $headers)->assertJsonPath('data.current_balance', '1000.00');
        $this->putJson("/api/loans/$id", ['installments' => 4], $headers)->assertUnprocessable();
        $this->getJson("/api/loans/$id", $this->apiHeaders(User::factory()->create()))->assertNotFound();
    }

    public function test_amortization_keeps_schedule_and_payoff_can_be_reversed(): void
    {
        [$user,$headers,$account,$id] = $this->fixture();
        $this->postJson("/api/loans/$id/activate", [], $headers)->assertOk();
        $this->postJson("/api/loans/$id/balances", ['outstanding_balance' => '220.00', 'reported_at' => now()->toDateString()], $headers)->assertCreated();
        $amort = $this->postJson("/api/loans/$id/payments", ['payment_type' => 'AMORTIZACAO', 'amount' => '20.00', 'payment_date' => '2027-01-01'], $headers)->assertCreated()->json('data.id');
        $this->getJson("/api/loans/$id", $headers)->assertJsonPath('data.remaining_installments', 3)->assertJsonPath('data.outstanding_balance', '220.00');
        $payoff = $this->postJson("/api/loans/$id/payments", ['payment_type' => 'QUITACAO', 'amount' => '200.00', 'payment_date' => '2027-01-02'], $headers)->assertCreated()->json('data.id');
        $this->getJson("/api/loans/$id", $headers)->assertJsonPath('data.status', 'QUITADO')->assertJsonPath('data.remaining_installments', 0)->assertJsonPath('data.outstanding_balance', '0.00');
        $this->deleteJson('/api/loan-payments/'.$amort, [], $headers)->assertConflict();
        $this->deleteJson('/api/loan-payments/'.$payoff, [], $headers)->assertOk();
        $this->getJson("/api/loans/$id", $headers)->assertJsonPath('data.remaining_installments', 3)->assertJsonPath('data.outstanding_balance', '220.00');
        $this->getJson('/api/accounts/'.$account->id, $headers)->assertJsonPath('data.current_balance', '980.00');
    }

    public function test_invalid_contracts_and_foreign_payments_leave_no_partial_writes(): void
    {
        [$user, $headers, $account, $id] = $this->fixture();
        $this->postJson("/api/loans/$id/activate", ['end_date' => '2027-04-30'], $headers)->assertUnprocessable();
        $this->assertSame(0, $user->transactions()->count());
        $loan = $this->postJson("/api/loans/$id/activate", [], $headers)->assertOk()->json('data');
        $other = User::factory()->create();
        $foreign = $other->accounts()->create(['name' => 'Outra conta', 'account_type' => 'CARTEIRA']);
        $this->postJson("/api/loans/$id/payments", ['payment_type' => 'AMORTIZACAO', 'amount' => '10.00', 'payment_date' => '2027-01-31', 'account_id' => $foreign->id], $headers)->assertUnprocessable();
        $this->postJson("/api/loans/$id/payments", ['payment_type' => 'PARCELA', 'loan_installment_id' => $loan['schedule'][0]['id'], 'amount' => '50.00', 'payment_date' => '2027-01-31'], $headers)->assertUnprocessable();
        $this->postJson("/api/loans/$id/balances", ['outstanding_balance' => '200.00', 'reported_at' => now()->toDateString()], $this->apiHeaders($other))->assertNotFound();
        $this->assertSame(0, $user->loanPayments()->count());
        $this->assertSame(3, $user->transactions()->where('status', 'PENDENTE')->count());
    }

    public function test_payroll_requires_explicit_net_income_confirmation(): void
    {
        [$user,$headers,$account,$id] = $this->fixture();
        $data = ['loan_type' => 'CONSIGNADO', 'cash_flow_mode' => 'FOLHA', 'account_id' => null];
        $this->postJson("/api/loans/$id/activate", $data, $headers)->assertUnprocessable();
        $user->userPreferences()->create(['income_is_net_of_payroll_loan' => true, 'timezone' => 'America/Sao_Paulo', 'currency' => 'BRL']);
        $loan = $this->postJson("/api/loans/$id/activate", $data, $headers)->assertOk()->json('data');
        $this->postJson("/api/loans/$id/payments", ['payment_type' => 'PARCELA', 'loan_installment_id' => $loan['schedule'][0]['id'], 'amount' => '100.00', 'payment_date' => '2027-01-31'], $headers)->assertCreated();
        $this->assertFalse($user->transactions()->first()->cash_flow_effect);
        $this->getJson('/api/accounts/'.$account->id, $headers)->assertJsonPath('data.current_balance', '1000.00');
    }
}
