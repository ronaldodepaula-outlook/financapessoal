<?php

namespace App\Http\Requests;

use App\Enums\LoanType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Loan;
use App\Rules\MoneyAmount;
use App\Rules\OwnedRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->user()) {
            return false;
        }
        if ($this->route('id')) {
            Loan::forUser($this->user()->id)->findOrFail($this->route('id'));
        }

        return true;
    }

    public function rules(): array
    {
        $r = $this->route()->getActionMethod() === 'store' ? 'required' : 'sometimes';
        $owned = fn ($model) => ['nullable', 'integer', new OwnedRecord($model, $this->user()->id)];
        $rate = ['nullable', 'numeric', 'between:0,9999.9999', 'decimal:0,4'];

        return ['name' => [$r, 'string', 'max:160'], 'institution' => 'nullable|string|max:120', 'loan_type' => [$r, Rule::enum(LoanType::class)],
            'principal_amount' => [$r, new MoneyAmount], 'installment_amount' => [$r, new MoneyAmount], 'installments' => [$r, 'integer', 'between:1,600'],
            'interest_rate' => $rate, 'cet' => $rate, 'annual_cet' => $rate, 'iof' => ['nullable', new MoneyAmount(true)],
            'start_date' => 'nullable|date_format:Y-m-d|after_or_equal:1900-01-01|before_or_equal:2200-12-31',
            'first_due_date' => 'nullable|date_format:Y-m-d|after_or_equal:1900-01-01|before_or_equal:2150-12-31', 'end_date' => 'nullable|date_format:Y-m-d|before_or_equal:2200-12-31',
            'category_id' => $owned(Category::class), 'subcategory_id' => $owned(Category::class), 'account_id' => $owned(Account::class),
            'cash_flow_mode' => 'nullable|in:CONTA,FOLHA', 'notes' => 'nullable|string|max:5000', 'status' => 'prohibited', 'user_id' => 'prohibited', 'template_key' => 'prohibited'];
    }
}
