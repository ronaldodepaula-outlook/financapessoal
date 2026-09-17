<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\TransactionType;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use BelongsToUser;

    protected $fillable = ['account_id', 'card_id', 'category_id', 'subcategory_id', 'merchant_id', 'card_invoice_id', 'transaction_type', 'description', 'transaction_date', 'due_date', 'competence_month', 'competence_year', 'amount', 'payment_method', 'is_fixed', 'is_installment', 'installment_id', 'installment_number', 'fixed_expense_id', 'subscription_id', 'income_schedule_id', 'status', 'paid_at', 'notes', 'import_fingerprint'];

    protected function casts(): array
    {
        return [
            'cash_flow_effect' => 'boolean',
            'transaction_type' => TransactionType::class,
            'transaction_date' => 'immutable_date:Y-m-d',
            'due_date' => 'immutable_date:Y-m-d',
            'competence_month' => 'integer',
            'competence_year' => 'integer',
            'amount' => 'decimal:2',
            'payment_method' => PaymentMethod::class,
            'is_fixed' => 'boolean',
            'is_installment' => 'boolean',
            'installment_number' => 'integer',
            'status' => PaymentStatus::class,
            'paid_at' => 'immutable_datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id')->withTrashed();
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class, 'card_id')->withTrashed();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id')->withTrashed();
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'subcategory_id')->withTrashed();
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class, 'merchant_id')->withTrashed();
    }

    public function cardInvoice(): BelongsTo
    {
        return $this->belongsTo(CardInvoice::class, 'card_invoice_id');
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(Installment::class, 'installment_id');
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class, 'loan_id');
    }

    public function fixedExpense(): BelongsTo
    {
        return $this->belongsTo(FixedExpense::class, 'fixed_expense_id')->withTrashed();
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_id')->withTrashed();
    }

    public function incomeSchedule(): BelongsTo
    {
        return $this->belongsTo(IncomeSchedule::class, 'income_schedule_id')->withTrashed();
    }
}
