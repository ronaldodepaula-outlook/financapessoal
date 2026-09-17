<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    private function category(User $user): Category
    {
        return $user->categories()->create(['name' => 'Supermercado', 'type' => 'DESPESA']);
    }

    private function account(User $user): Account
    {
        return $user->accounts()->create([
            'name' => 'Principal', 'account_type' => 'CONTA_CORRENTE', 'initial_balance' => '1200.01',
        ]);
    }

    private function transactionData(Category $category): array
    {
        return [
            'category_id' => $category->id,
            'transaction_type' => 'DESPESA',
            'description' => 'Compra de teste',
            'transaction_date' => '2027-01-10',
            'competence_month' => 1, 'competence_year' => 2027,
            'amount' => '1024.77', 'payment_method' => 'PIX',
        ];
    }

    public function test_all_financial_tables_exist_with_owner_and_timestamps(): void
    {
        $tables = [
            'user_preferences', 'accounts', 'cards', 'categories', 'merchants',
            'transactions', 'transfers', 'installments', 'fixed_expenses',
            'subscriptions', 'income_schedules', 'loans', 'loan_installments',
            'loan_payments', 'loan_balance_snapshots', 'card_invoices',
            'card_invoice_payments', 'budgets', 'budget_revisions',
            'financial_goals', 'goal_contributions', 'imports', 'import_rows', 'audit_logs',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasColumns($table, ['id', 'user_id', 'created_at', 'updated_at']), $table);
        }
    }

    public function test_relationships_preserve_money_and_owner_scope(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $account = $this->account($owner);
        $this->account($other);
        $transaction = $owner->transactions()->create([
            ...$this->transactionData($this->category($owner)), 'account_id' => $account->id,
        ]);

        $this->assertSame('1024.77', $transaction->fresh()->amount);
        $this->assertSame('1200.01', $account->fresh()->initial_balance);
        $this->assertTrue($transaction->user->is($owner));
        $this->assertTrue($account->transactions->first()->is($transaction));
        $this->assertSame(1, Account::forUser($owner->id)->count());
        $this->assertSame('2027-01-10', $transaction->transaction_date->format('Y-m-d'));
    }

    public function test_database_rejects_account_from_another_user(): void
    {
        $owner = User::factory()->create();
        $foreignAccount = $this->account(User::factory()->create());
        $data = $this->transactionData($this->category($owner));
        $this->expectException(QueryException::class);
        $owner->transactions()->create([...$data, 'account_id' => $foreignAccount->id]);
    }

    public function test_database_rejects_category_from_another_user(): void
    {
        $owner = User::factory()->create();
        $data = $this->transactionData($this->category(User::factory()->create()));
        $this->expectException(QueryException::class);
        $owner->transactions()->create($data);
    }

    public function test_database_requires_category_on_expense(): void
    {
        $owner = User::factory()->create();
        $data = $this->transactionData($this->category($owner));
        $this->expectException(QueryException::class);
        $owner->transactions()->create([...$data, 'category_id' => null]);
    }

    public function test_referenced_account_cannot_be_physically_deleted(): void
    {
        $owner = User::factory()->create();
        $account = $this->account($owner);
        $owner->transactions()->create([...$this->transactionData($this->category($owner)), 'account_id' => $account->id]);
        $this->expectException(QueryException::class);
        $account->forceDelete();
    }

    public function test_soft_deleted_account_remains_visible_in_transaction_history(): void
    {
        $owner = User::factory()->create();
        $account = $this->account($owner);
        $transaction = $owner->transactions()->create([...$this->transactionData($this->category($owner)), 'account_id' => $account->id]);
        $account->delete();
        $this->assertSame(0, Account::forUser($owner->id)->count());
        $this->assertTrue($transaction->fresh()->account->trashed());
        $this->assertSame(1, Transaction::forUser($owner->id)->count());
    }

    public function test_root_category_uniqueness_also_applies_to_null_parent(): void
    {
        $owner = User::factory()->create();
        $this->category($owner);
        $this->expectException(QueryException::class);
        $this->category($owner);
    }

    public function test_budget_uniqueness_also_applies_to_null_subcategory(): void
    {
        $owner = User::factory()->create();
        $data = ['category_id' => $this->category($owner)->id, 'year' => 2027, 'month' => 1, 'planned_amount' => '2400.00'];
        $owner->budgets()->create($data);
        $this->expectException(QueryException::class);
        $owner->budgets()->create($data);
    }

    public function test_budget_periods_and_revisions_preserve_previous_values(): void
    {
        $owner = User::factory()->create();
        $data = ['category_id' => $this->category($owner)->id, 'month' => 1, 'planned_amount' => '2400.00'];
        $previous = $owner->budgets()->create([...$data, 'year' => 2027]);
        $next = $owner->budgets()->create([...$data, 'year' => 2028, 'planned_amount' => '2500.00']);
        $owner->budgetRevisions()->create([
            'budget_id' => $next->id, 'version' => 1,
            'previous_amount' => '2400.00', 'new_amount' => '2500.00',
        ]);

        $this->assertSame('2400.00', $previous->fresh()->planned_amount);
        $this->assertSame('2400.00', $next->revisions->first()->previous_amount);
        $this->assertSame(2, Budget::forUser($owner->id)->count());
    }

    public function test_transfer_and_invoice_payment_do_not_create_expense_records(): void
    {
        $owner = User::factory()->create();
        $first = $this->account($owner);
        $second = $this->account($owner);
        $card = $owner->cards()->create(['name' => 'Cartão', 'credit_limit' => '5000.00', 'closing_day' => 20, 'due_day' => 27]);
        $invoice = $owner->cardInvoices()->create([
            'card_id' => $card->id, 'competence_year' => 2027, 'competence_month' => 1,
            'closing_date' => '2027-01-20', 'due_date' => '2027-01-27',
        ]);
        $owner->transfers()->create([
            'source_account_id' => $first->id, 'destination_account_id' => $second->id,
            'amount' => '100.00', 'transfer_date' => '2027-01-10', 'status' => 'PAGA',
        ]);
        $owner->cardInvoicePayments()->create([
            'card_invoice_id' => $invoice->id, 'account_id' => $first->id,
            'amount' => '500.00', 'payment_date' => '2027-01-27', 'status' => 'PAGA',
        ]);

        $this->assertSame(0, $owner->transactions()->count());
        $this->assertSame(1, $first->outgoingTransfers()->count());
        $this->assertSame('500.00', $invoice->payments->first()->amount);
    }

    public function test_installment_number_cannot_be_repeated_within_a_purchase(): void
    {
        $owner = User::factory()->create();
        $category = $this->category($owner);
        $purchase = $owner->installments()->create([
            'category_id' => $category->id, 'description' => 'Notebook',
            'total_amount' => '1200.00', 'installment_amount' => '100.00', 'total_installments' => 12,
            'start_date' => '2027-01-10', 'end_date' => '2027-12-10',
        ]);
        $data = [...$this->transactionData($category), 'installment_id' => $purchase->id, 'is_installment' => true, 'installment_number' => 1];
        $owner->transactions()->create($data);
        $this->expectException(QueryException::class);
        $owner->transactions()->create($data);
    }

    public function test_fixed_forecast_is_unique_per_competence(): void
    {
        $owner = User::factory()->create();
        $category = $this->category($owner);
        $fixed = $owner->fixedExpenses()->create([
            'category_id' => $category->id, 'description' => 'Internet', 'amount' => '99.90',
            'due_day' => 10, 'payment_method' => 'BOLETO', 'start_date' => '2027-01-01',
        ]);
        $data = [...$this->transactionData($category), 'fixed_expense_id' => $fixed->id, 'is_fixed' => true];
        $owner->transactions()->create($data);
        $this->expectException(QueryException::class);
        $owner->transactions()->create($data);
    }

    public function test_import_fingerprint_rejects_duplicates_but_manual_entries_can_repeat(): void
    {
        $owner = User::factory()->create();
        $data = $this->transactionData($this->category($owner));
        $owner->transactions()->create($data);
        $owner->transactions()->create($data);
        $fingerprint = hash('sha256', 'test-row');
        $owner->transactions()->create([...$data, 'import_fingerprint' => $fingerprint]);
        $this->assertSame(3, $owner->transactions()->count());
        $this->expectException(QueryException::class);
        $owner->transactions()->create([...$data, 'import_fingerprint' => $fingerprint]);
    }

    public function test_loan_can_remain_draft_until_dates_are_confirmed(): void
    {
        $owner = User::factory()->create();
        $loan = $owner->loans()->create([
            'name' => 'Consignado', 'loan_type' => 'CONSIGNADO', 'principal_amount' => '17000.00',
            'installments' => 24, 'installment_amount' => '1024.77', 'interest_rate' => '2.5400',
            'cet' => '2.7900', 'annual_cet' => '39.0500', 'iof' => '530.18',
        ])->fresh();
        $this->assertNull($loan->first_due_date);
        $this->assertSame('RASCUNHO', $loan->status->value);
        $this->assertSame('1024.77', $loan->installment_amount);
        $this->assertSame('2.7900', $loan->cet);
        $this->assertCount(0, $loan->schedule);
        $this->assertSame(0, Transaction::count());
    }

    public function test_seed_does_not_create_default_credentials_or_financial_facts(): void
    {
        $this->seed();
        $this->assertSame(0, User::count());
        $this->assertSame(0, Transaction::count());
    }
}
