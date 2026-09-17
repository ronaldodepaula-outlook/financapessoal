<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Card;
use App\Models\CardInvoice;
use App\Models\Category;
use App\Models\Merchant;
use App\Models\Transaction;
use App\Rules\MoneyAmount;
use App\Rules\OwnedRecord;
use App\Services\FinancialReferences;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->user()) {
            return false;
        }
        if ($this->route('id')) {
            Transaction::forUser($this->user()->id)->findOrFail($this->route('id'));
        }

        return true;
    }

    public function rules(): array
    {
        $r = $this->isMethod('POST') ? 'required' : 'sometimes';
        $owned = fn ($model) => ['nullable', 'integer', new OwnedRecord($model, $this->user()->id)];

        return [
            'description' => [$r, 'string', 'max:255'], 'transaction_type' => [$r, Rule::enum(TransactionType::class)],
            'account_id' => $owned(Account::class), 'card_id' => $owned(Card::class), 'merchant_id' => $owned(Merchant::class),
            'category_id' => [$r, 'integer', new OwnedRecord(Category::class, $this->user()->id)], 'subcategory_id' => $owned(Category::class),
            'card_invoice_id' => $owned(CardInvoice::class), 'amount' => [$r, new MoneyAmount],
            'transaction_date' => [$r, 'date_format:Y-m-d'], 'due_date' => ['nullable', 'date_format:Y-m-d'],
            'competence_year' => [$r, 'integer', 'between:1900,2200'], 'competence_month' => [$r, 'integer', 'between:1,12'],
            'payment_method' => [$r, Rule::enum(PaymentMethod::class)], 'status' => ['sometimes', Rule::enum(PaymentStatus::class)],
            'is_fixed' => 'sometimes|boolean', 'notes' => 'nullable|string|max:5000',
            'loan_id' => 'prohibited', 'cash_flow_effect' => 'prohibited',
            'user_id' => 'prohibited', 'is_installment' => 'prohibited', 'installment_id' => 'prohibited', 'installment_number' => 'prohibited',
            'fixed_expense_id' => 'prohibited', 'subscription_id' => 'prohibited', 'income_schedule_id' => 'prohibited', 'paid_at' => 'prohibited', 'import_fingerprint' => 'prohibited',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $current = $this->route('id') ? Transaction::forUser($this->user()->id)->findOrFail($this->route('id')) : null;
            app(FinancialReferences::class)->validate($this->user(), array_replace($current?->attributesToArray() ?? [], $this->validated()));
        }];
    }
}
