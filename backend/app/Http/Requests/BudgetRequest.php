<?php

namespace App\Http\Requests;

use App\Models\Budget;
use App\Models\Category;
use App\Rules\MoneyAmount;
use App\Rules\OwnedRecord;
use Illuminate\Foundation\Http\FormRequest;

class BudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->user()) {
            return false;
        }
        if ($this->route('id')) {
            Budget::forUser($this->user()->id)->findOrFail($this->route('id'));
        }

        return true;
    }

    public function rules(): array
    {
        $r = $this->isMethod('POST') ? 'required' : 'prohibited';

        return ['year' => [$r, 'integer', 'between:1900,2200'], 'month' => [$r, 'integer', 'between:1,12'],
            'category_id' => [$r, 'integer', new OwnedRecord(Category::class, $this->user()->id)],
            'subcategory_id' => [$this->isMethod('POST') ? 'nullable' : 'prohibited', 'integer', new OwnedRecord(Category::class, $this->user()->id)],
            'planned_amount' => ['required', new MoneyAmount(true)], 'reason' => 'nullable|string|max:1000', 'user_id' => 'prohibited'];
    }
}
