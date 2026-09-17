<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

class FinancialMovementsTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    private function setupLedger(): array
    {
        $user = User::factory()->create();
        $headers = $this->apiHeaders($user);
        $account = $user->accounts()->create(['name' => 'Principal', 'account_type' => 'CONTA_CORRENTE', 'initial_balance' => '1000.00']);
        $category = $user->categories()->create(['name' => 'Alimentação', 'type' => 'DESPESA']);

        return [$user, $headers, $account, $category];
    }

    private function expense($account, $category): array
    {
        return ['description' => 'Compra', 'account_id' => $account->id, 'category_id' => $category->id, 'transaction_type' => 'DESPESA', 'amount' => '100.10', 'payment_method' => 'PIX', 'transaction_date' => '2027-01-10', 'competence_year' => 2027, 'competence_month' => 1, 'status' => 'PAGA'];
    }

    public function test_income_expense_edits_and_cancellation_change_balance_once(): void
    {
        [$user,$headers,$account,$category] = $this->setupLedger();
        $income = $user->categories()->create(['name' => 'Renda', 'type' => 'RECEITA']);
        $expense = $this->postJson('/api/transactions', $this->expense($account, $category), $headers)->assertCreated()->json('data.id');
        $this->postJson('/api/transactions', [...$this->expense($account, $income), 'transaction_type' => 'RECEITA', 'amount' => '300.20'], $headers)->assertCreated();
        $this->getJson('/api/accounts/'.$account->id, $headers)->assertOk()->assertJsonPath('data.current_balance', '1200.10');
        $this->putJson('/api/transactions/'.$expense, ['amount' => '200.00'], $headers)->assertOk();
        $this->getJson('/api/accounts/'.$account->id, $headers)->assertOk()->assertJsonPath('data.current_balance', '1100.20');
        $this->deleteJson('/api/transactions/'.$expense, [], $headers)->assertOk();
        $this->deleteJson('/api/transactions/'.$expense, [], $headers)->assertOk();
        $this->getJson('/api/accounts/'.$account->id, $headers)->assertOk()->assertJsonPath('data.current_balance', '1300.20');
        $this->getJson('/api/transactions?status=CANCELADA&search=Compra', $headers)->assertOk()->assertJsonPath('data.pagination.total', 1);
        $this->getJson('/api/transactions?date_to=2027-01-31', $headers)->assertOk();
        $this->putJson('/api/accounts/'.$account->id, ['initial_balance' => '500.00'], $headers)->assertUnprocessable();
    }

    public function test_transfers_move_two_balances_without_changing_income_or_expenses(): void
    {
        [$user,$headers,$account,$category] = $this->setupLedger();
        $destination = $user->accounts()->create(['name' => 'Poupança', 'account_type' => 'POUPANCA', 'initial_balance' => '0.00']);
        $data = ['source_account_id' => $account->id, 'destination_account_id' => $destination->id, 'amount' => '250.55', 'transfer_date' => '2027-01-15', 'status' => 'PAGA'];
        $id = $this->postJson('/api/transfers', $data, $headers)->assertCreated()->json('data.id');
        $this->getJson('/api/accounts/'.$account->id, $headers)->assertJsonPath('data.current_balance', '749.45');
        $this->getJson('/api/accounts/'.$destination->id, $headers)->assertJsonPath('data.current_balance', '250.55');
        $this->assertSame(0, $user->transactions()->count());
        $this->postJson('/api/transfers', [...$data, 'destination_account_id' => $account->id], $headers)->assertUnprocessable();
        $this->deleteJson('/api/transfers/'.$id, [], $headers)->assertOk();
        $this->getJson('/api/accounts/'.$account->id, $headers)->assertJsonPath('data.current_balance', '1000.00');
    }

    public function test_invoice_payment_reduces_bank_and_credit_liability_without_duplicate_expense(): void
    {
        [$user,$headers,$account,$category] = $this->setupLedger();
        $card = $user->cards()->create(['name' => 'Cartão', 'credit_limit' => '3000.00', 'closing_day' => 20, 'due_day' => 27]);
        $invoice = $this->postJson('/api/card-invoices', ['card_id' => $card->id, 'competence_year' => 2027, 'competence_month' => 1, 'closing_date' => '2027-01-20', 'due_date' => '2027-01-27'], $headers)->assertCreated()->json('data.id');
        $purchase = $this->postJson('/api/transactions', [...$this->expense($account, $category), 'account_id' => null, 'card_id' => $card->id, 'card_invoice_id' => $invoice, 'payment_method' => 'CREDITO', 'amount' => '500.00'], $headers)->assertCreated()->json('data.id');
        $this->getJson('/api/accounts/'.$account->id, $headers)->assertJsonPath('data.current_balance', '1000.00');
        $this->getJson('/api/cards/'.$card->id, $headers)->assertJsonPath('data.used_limit', '500.00');
        $payment = $this->postJson('/api/card-invoices/'.$invoice.'/payments', ['account_id' => $account->id, 'amount' => '500.00', 'payment_date' => '2027-01-27'], $headers)->assertCreated()->json('data.id');
        $this->getJson('/api/accounts/'.$account->id, $headers)->assertJsonPath('data.current_balance', '500.00');
        $this->getJson('/api/card-invoices/'.$invoice, $headers)->assertJsonPath('data.outstanding_amount', '0.00');
        $this->getJson('/api/cards/'.$card->id, $headers)->assertJsonPath('data.available_limit', '3000.00');
        $this->assertSame(1, $user->transactions()->count());
        $this->assertSame('500.00', $user->transactions()->first()->amount);
        $this->postJson('/api/card-invoices/'.$invoice.'/payments', ['account_id' => $account->id, 'amount' => '0.01', 'payment_date' => '2027-01-27'], $headers)->assertUnprocessable();
        $other = $user->categories()->create(['name' => 'Outra', 'type' => 'DESPESA']);
        $this->putJson('/api/transactions/'.$purchase, ['category_id' => $other->id, 'description' => 'Compra revisada'], $headers)->assertOk();
        $this->putJson('/api/transactions/'.$purchase, ['amount' => '400.00'], $headers)->assertConflict();
        $this->deleteJson('/api/transactions/'.$purchase, [], $headers)->assertConflict();
        $this->deleteJson('/api/card-invoice-payments/'.$payment, [], $headers)->assertOk();
        $this->getJson('/api/accounts/'.$account->id, $headers)->assertJsonPath('data.current_balance', '1000.00');
        $this->getJson('/api/card-invoices/'.$invoice, $headers)->assertJsonPath('data.outstanding_amount', '500.00');
    }

    public function test_card_purchases_auto_link_to_matching_invoice_by_competence(): void
    {
        [$user,$headers,$account,$category] = $this->setupLedger();
        $card = $user->cards()->create(['name' => 'Cartão', 'credit_limit' => '3000.00', 'closing_day' => 20, 'due_day' => 27]);
        $invoice = $this->postJson('/api/card-invoices', ['card_id' => $card->id, 'competence_year' => 2027, 'competence_month' => 1, 'closing_date' => '2027-01-20', 'due_date' => '2027-01-27'], $headers)->assertCreated()->json('data.id');
        $this->postJson('/api/transactions', [...$this->expense($account, $category), 'account_id' => null, 'card_id' => $card->id, 'payment_method' => 'CREDITO', 'amount' => '150.00'], $headers)->assertCreated();
        $this->getJson('/api/card-invoices/'.$invoice, $headers)->assertJsonPath('data.total_amount', '150.00');
        $this->postJson('/api/transactions', [...$this->expense($account, $category), 'account_id' => null, 'card_id' => $card->id, 'payment_method' => 'CREDITO', 'amount' => '80.00', 'competence_year' => 2027, 'competence_month' => 2, 'transaction_date' => '2027-02-05'], $headers)->assertCreated();
        $secondInvoice = $this->postJson('/api/card-invoices', ['card_id' => $card->id, 'competence_year' => 2027, 'competence_month' => 2, 'closing_date' => '2027-02-20', 'due_date' => '2027-02-27'], $headers)->assertCreated()->json('data.id');
        $this->getJson('/api/card-invoices/'.$secondInvoice, $headers)->assertJsonPath('data.total_amount', '80.00');
        $result = $this->postJson('/api/card-invoices/'.$secondInvoice.'/link-transactions', [], $headers)->assertOk()->json('data');
        $this->assertSame(0, $result['linked']);
    }

    public function test_financial_validation_prevents_cross_user_links_wrong_categories_and_invalid_dates(): void
    {
        [$user,$headers,$account,$category] = $this->setupLedger();
        $foreign = User::factory()->create();
        $foreignAccount = $foreign->accounts()->create(['name' => 'Outra', 'account_type' => 'CARTEIRA']);
        $data = $this->expense($account, $category);
        $this->postJson('/api/transactions', [...$data, 'account_id' => $foreignAccount->id], $headers)->assertUnprocessable();
        $this->postJson('/api/transactions', [...$data, 'transaction_type' => 'RECEITA'], $headers)->assertUnprocessable();
        $this->postJson('/api/transactions', [...$data, 'transaction_date' => '2027-02-30'], $headers)->assertUnprocessable();
        $this->postJson('/api/transactions', [...$data, 'amount' => '-1.00'], $headers)->assertUnprocessable();
        $this->postJson('/api/transactions', [...$data, 'amount' => 0.1], $headers)->assertUnprocessable();
        $id = $this->postJson('/api/transactions', $data, $headers)->assertCreated()->json('data.id');
        $this->putJson('/api/transactions/'.$id, ['amount' => '1.00'], $this->apiHeaders($foreign))->assertNotFound();
        $this->deleteJson('/api/transactions/'.$id, [], $this->apiHeaders($foreign))->assertNotFound();
    }
}
