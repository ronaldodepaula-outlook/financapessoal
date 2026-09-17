<?php

namespace App\Http\Requests;

use App\Enums\BillingCycle;
use App\Enums\PaymentMethod;
use App\Models\Account;
use App\Models\Card;
use App\Models\Category;
use App\Repositories\RecurrenceRepository;
use App\Rules\MoneyAmount;
use App\Rules\OwnedRecord;
use App\Services\RecurrenceService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RecurrenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->user()) {
            return false;
        }
        if ($this->route('id')) {
            app(RecurrenceRepository::class)->query($this->route('recurrence'), $this->user()->id)->findOrFail($this->route('id'));
        }

        return true;
    }

    public function rules(): array
    {
        $r = $this->isMethod('POST') ? 'required' : 'sometimes';
        $owned = fn ($model) => ['nullable', 'integer', new OwnedRecord($model, $this->user()->id)];
        $base = ['amount' => [$r, new MoneyAmount], 'active' => 'sometimes|boolean', 'account_id' => $owned(Account::class), 'category_id' => $owned(Category::class),
            'start_date' => 'nullable|date_format:Y-m-d|after_or_equal:1900-01-01|before_or_equal:2200-12-31', 'end_date' => 'nullable|date_format:Y-m-d|before_or_equal:2200-12-31', 'user_id' => 'prohibited', 'template_key' => 'prohibited', 'billing_anchor_date' => 'prohibited'];
        if ($this->route('recurrence') === 'income-schedules') {
            return [...$base, 'description' => [$r, 'string', 'max:255'], 'day' => 'nullable|integer|between:1,31', 'period' => [$r, 'in:01_15,16_31']];
        }
        $base = [...$base, 'card_id' => $owned(Card::class), 'subcategory_id' => $owned(Category::class), 'payment_method' => [$r, Rule::enum(PaymentMethod::class)],
            'category_id' => [$r, 'integer', new OwnedRecord(Category::class, $this->user()->id)], 'start_date' => [$r, 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:2200-12-31']];

        return $this->route('recurrence') === 'fixed-expenses'
            ? [...$base, 'description' => [$r, 'string', 'max:255'], 'due_day' => [$r, 'integer', 'between:1,31']]
            : [...$base, 'name' => [$r, 'string', 'max:160'], 'billing_cycle' => [$r, Rule::enum(BillingCycle::class)], 'next_due_date' => [$r, 'date_format:Y-m-d', 'before_or_equal:2200-12-31']];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            $resource = $this->route('recurrence');
            $old = $this->route('id') ? app(RecurrenceRepository::class)->query($resource, $this->user()->id)->findOrFail($this->route('id'))->attributesToArray() : [];
            app(RecurrenceService::class)->validate($this->user(), $resource, array_replace($old, $this->validated()));
        }];
    }
}
