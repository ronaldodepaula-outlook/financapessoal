<?php

namespace App\Http\Requests;

use App\Models\Card;
use App\Rules\OwnedRecord;
use Illuminate\Foundation\Http\FormRequest;

class InvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'card_id' => ['required', 'integer', new OwnedRecord(Card::class, $this->user()->id)],
            'competence_year' => 'required|integer|between:1900,2200', 'competence_month' => 'required|integer|between:1,12',
            'closing_date' => 'required|date_format:Y-m-d', 'due_date' => 'required|date_format:Y-m-d|after_or_equal:closing_date', 'user_id' => 'prohibited',
        ];
    }
}
