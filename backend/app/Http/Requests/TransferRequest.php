<?php

namespace App\Http\Requests;

use App\Models\Account;
use App\Models\Transfer;
use App\Rules\MoneyAmount;
use App\Rules\OwnedRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class TransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->user()) {
            return false;
        }
        if ($this->route('id')) {
            Transfer::forUser($this->user()->id)->findOrFail($this->route('id'));
        }

        return true;
    }

    public function rules(): array
    {
        $r = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'source_account_id' => [$r, 'integer', new OwnedRecord(Account::class, $this->user()->id)],
            'destination_account_id' => [$r, 'integer', new OwnedRecord(Account::class, $this->user()->id)],
            'amount' => [$r, new MoneyAmount], 'transfer_date' => [$r, 'date_format:Y-m-d'],
            'description' => 'nullable|string|max:255', 'status' => 'sometimes|in:PENDENTE,PAGA,CANCELADA', 'notes' => 'nullable|string|max:5000', 'user_id' => 'prohibited',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $old = $this->route('id') ? Transfer::forUser($this->user()->id)->findOrFail($this->route('id'))->attributesToArray() : [];
            $data = array_replace($old, $this->validated());
            if ($data['source_account_id'] == $data['destination_account_id']) {
                $validator->errors()->add('destination_account_id', 'Origem e destino devem ser contas diferentes.');
            }
        }];
    }
}
