<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoanInstallment extends Model
{
    use BelongsToUser;

    protected $fillable = ['loan_id', 'transaction_id', 'number', 'due_date', 'amount', 'principal_component', 'interest_component', 'status', 'paid_at'];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'due_date' => 'immutable_date:Y-m-d',
            'amount' => 'decimal:2',
            'principal_component' => 'decimal:2',
            'interest_component' => 'decimal:2',
            'status' => PaymentStatus::class,
            'paid_at' => 'immutable_datetime',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class, 'loan_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(LoanPayment::class, 'loan_installment_id');
    }
}
