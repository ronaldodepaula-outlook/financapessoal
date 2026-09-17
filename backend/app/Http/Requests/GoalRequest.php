<?php

namespace App\Http\Requests;

use App\Models\FinancialGoal;
use App\Rules\MoneyAmount;
use Illuminate\Foundation\Http\FormRequest;

class GoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->user()) {
            return false;
        }
        if ($this->route('id')) {
            FinancialGoal::forUser($this->user()->id)->findOrFail($this->route('id'));
        }

        return true;
    }

    public function rules(): array
    {
        $r = $this->isMethod('POST') ? 'required' : 'sometimes';

        return ['name' => [$r, 'string', 'max:160'], 'target_amount' => [$r, new MoneyAmount], 'initial_amount' => ['sometimes', new MoneyAmount(true)],
            'target_date' => 'nullable|date_format:Y-m-d', 'status' => 'sometimes|in:ATIVA,CONCLUIDA', 'notes' => 'nullable|string|max:5000', 'user_id' => 'prohibited'];
    }
}
