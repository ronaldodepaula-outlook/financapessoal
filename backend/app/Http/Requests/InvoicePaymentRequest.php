<?php

namespace App\Http\Requests;

use App\Models\Account;
use App\Models\CardInvoice;
use App\Rules\MoneyAmount;
use App\Rules\OwnedRecord;
use Illuminate\Foundation\Http\FormRequest;

class InvoicePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->user()) {
            return false;
        }
        CardInvoice::forUser($this->user()->id)->findOrFail($this->route('id'));

        return true;
    }

    public function rules(): array
    {
        return [
            'account_id' => ['required', 'integer', new OwnedRecord(Account::class, $this->user()->id)],
            'amount' => ['required', new MoneyAmount], 'payment_date' => 'required|date_format:Y-m-d',
            'notes' => 'nullable|string|max:5000', 'user_id' => 'prohibited', 'status' => 'prohibited',
        ];
    }
}
