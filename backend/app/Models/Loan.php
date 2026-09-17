<?php

namespace App\Models;

use App\Enums\LoanStatus;
use App\Enums\LoanType;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Loan extends Model
{
    use BelongsToUser;

    protected $fillable = ['name', 'institution', 'loan_type', 'principal_amount', 'interest_rate', 'cet', 'annual_cet', 'iof', 'installments', 'installment_amount', 'start_date', 'first_due_date', 'end_date', 'status', 'notes', 'category_id', 'subcategory_id', 'account_id', 'cash_flow_mode'];

    protected function casts(): array
    {
        return [
            'loan_type' => LoanType::class,
            'principal_amount' => 'decimal:2',
            'interest_rate' => 'decimal:4',
            'cet' => 'decimal:4',
            'annual_cet' => 'decimal:4',
            'iof' => 'decimal:2',
            'installments' => 'integer',
            'installment_amount' => 'decimal:2',
            'start_date' => 'immutable_date:Y-m-d',
            'first_due_date' => 'immutable_date:Y-m-d',
            'end_date' => 'immutable_date:Y-m-d',
            'status' => LoanStatus::class,
        ];
    }

    public function schedule(): HasMany
    {
        return $this->hasMany(LoanInstallment::class, 'loan_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(LoanPayment::class, 'loan_id');
    }

    public function balanceSnapshots(): HasMany
    {
        return $this->hasMany(LoanBalanceSnapshot::class, 'loan_id');
    }
}
