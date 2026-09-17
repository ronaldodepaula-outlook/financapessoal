<?php

namespace Tests\Unit;

use App\Enums\PaymentStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;

class ModelContractTest extends TestCase
{
    public function test_money_casts_are_decimal_strings_and_not_floats(): void
    {
        $transaction = new Transaction(['amount' => '1024.77']);
        $account = new Account(['initial_balance' => '0.10']);
        $this->assertSame('1024.77', $transaction->amount);
        $this->assertSame('0.10', $account->initial_balance);
        $this->assertSame('decimal:2', $transaction->getCasts()['amount']);
    }

    public function test_owner_and_primary_key_are_not_mass_assignable(): void
    {
        $model = new Transaction;
        $this->assertFalse($model->isFillable('user_id'));
        $this->assertFalse($model->isFillable('id'));
        $this->assertFalse((new User)->isFillable('status'));
        Model::preventSilentlyDiscardingAttributes();
        $this->expectException(MassAssignmentException::class);
        $model->fill(['user_id' => 999]);
    }

    public function test_user_credentials_are_hidden_in_serialization(): void
    {
        $user = new User;
        $user->setRawAttributes(['name' => 'Teste', 'password' => 'sensitive-hash', 'remember_token' => 'sensitive-token']);
        $this->assertSame(['name' => 'Teste'], $user->toArray());
    }

    public function test_transaction_enums_do_not_classify_transfers_as_income_or_expense(): void
    {
        $this->assertSame(['RECEITA', 'DESPESA'], array_column(TransactionType::cases(), 'value'));
        $transaction = new Transaction(['status' => 'PENDENTE', 'transaction_type' => 'DESPESA']);
        $this->assertSame(PaymentStatus::PENDING, $transaction->status);
        $this->assertSame(TransactionType::EXPENSE, $transaction->transaction_type);
    }
}
