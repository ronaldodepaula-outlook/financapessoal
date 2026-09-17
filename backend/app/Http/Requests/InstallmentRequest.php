<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Models\Account;
use App\Models\Card;
use App\Models\Category;
use App\Rules\MoneyAmount;
use App\Rules\OwnedRecord;
use App\Services\FinancialReferences;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class InstallmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $owned = fn ($model) => ['nullable', 'integer', new OwnedRecord($model, $this->user()->id)];

        return [
            'description' => 'required|string|max:255', 'total_amount' => ['required', new MoneyAmount],
            'total_installments' => 'required|integer|between:1,600', 'start_date' => 'required|date_format:Y-m-d|after_or_equal:1900-01-01|before_or_equal:2150-12-31',
            'account_id' => $owned(Account::class), 'card_id' => $owned(Card::class),
            'category_id' => ['required', 'integer', new OwnedRecord(Category::class, $this->user()->id)], 'subcategory_id' => $owned(Category::class),
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)], 'notes' => 'nullable|string|max:5000',
            'user_id' => 'prohibited', 'installment_amount' => 'prohibited', 'end_date' => 'prohibited', 'status' => 'prohibited',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $data = $this->validated();
            if (bccomp(bcmul($data['total_amount'], '100', 0), (string) $data['total_installments'], 0) < 0) {
                $validator->errors()->add('total_amount', 'Cada parcela deve ter pelo menos um centavo.');
            }
            app(FinancialReferences::class)->validate($this->user(), [...$data, 'transaction_type' => 'DESPESA']);
        }];
    }
}
