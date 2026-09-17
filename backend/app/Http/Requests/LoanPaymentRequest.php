<?php

namespace App\Http\Requests;

use App\Models\Account;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Rules\MoneyAmount;
use App\Rules\OwnedRecord;
use Illuminate\Foundation\Http\FormRequest;

class LoanPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->user()) {
            return false;
        }
        Loan::forUser($this->user()->id)->findOrFail($this->route('id'));

        return true;
    }

    public function rules(): array
    {
        return ['payment_type' => 'required|in:PARCELA,AMORTIZACAO,QUITACAO', 'amount' => ['required', new MoneyAmount],
            'loan_installment_id' => ['nullable', 'required_if:payment_type,PARCELA', 'prohibited_unless:payment_type,PARCELA', 'integer', new OwnedRecord(LoanInstallment::class, $this->user()->id)],
            'account_id' => ['nullable', 'integer', new OwnedRecord(Account::class, $this->user()->id)], 'payment_date' => 'required|date_format:Y-m-d',
            'reference' => 'nullable|string|max:120', 'notes' => 'nullable|string|max:5000', 'user_id' => 'prohibited', 'transaction_id' => 'prohibited', 'status' => 'prohibited'];
    }
}
