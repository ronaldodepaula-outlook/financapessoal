<?php

namespace App\Models;

use App\Enums\LoanPaymentType;
use App\Enums\PaymentStatus;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanPayment extends Model
{
    use BelongsToUser;

    protected $fillable = ['loan_id', 'loan_installment_id', 'account_id', 'payment_type', 'amount', 'payment_date', 'reference', 'status', 'notes', 'transaction_id'];

    protected function casts(): array
    {
        return [
            'payment_type' => LoanPaymentType::class,
            'amount' => 'decimal:2',
            'payment_date' => 'immutable_date:Y-m-d',
            'status' => PaymentStatus::class,
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class, 'loan_id');
    }

    public function loanInstallment(): BelongsTo
    {
        return $this->belongsTo(LoanInstallment::class, 'loan_installment_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id')->withTrashed();
    }
}
