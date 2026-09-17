<?php

namespace App\Http\Requests;

use App\Enums\AccountType;
use App\Enums\RecordStatus;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Repositories\CatalogRepository;
use App\Rules\MoneyAmount;
use App\Rules\OwnedRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->user()) {
            return false;
        }
        if ($this->route('id')) {
            app(CatalogRepository::class)->query($this->route('catalog'), $this->user()->id)->findOrFail($this->route('id'));
        }

        return true;
    }

    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';
        $common = ['name' => [$required, 'string', 'max:120'], 'status' => ['sometimes', Rule::enum(RecordStatus::class)], 'user_id' => 'prohibited', 'id' => 'prohibited'];
        $category = ['nullable', 'integer', new OwnedRecord(Category::class, $this->user()->id)];

        return match ($this->route('catalog')) {
            'accounts' => [...$common, 'institution' => 'nullable|string|max:120', 'account_type' => [$required, Rule::enum(AccountType::class)], 'initial_balance' => ['sometimes', new MoneyAmount(true, true)], 'current_balance' => 'prohibited'],
            'cards' => [...$common, 'institution' => 'nullable|string|max:120', 'brand' => 'nullable|string|max:40', 'last_digits' => 'nullable|string|regex:/^\d{4}$/', 'credit_limit' => [$required, new MoneyAmount(true)], 'closing_day' => [$required, 'integer', 'between:1,31'], 'due_day' => [$required, 'integer', 'between:1,31']],
            'categories' => [...$common, 'type' => [$required, Rule::enum(TransactionType::class)], 'parent_id' => $category],
            'merchants' => [...$common, 'name' => [$required, 'string', 'max:160'], 'category_id' => $category, 'subcategory_id' => $category, 'normalized_name' => 'prohibited'],
        };
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $catalog = $this->route('catalog');
            $current = $this->route('id') ? app(CatalogRepository::class)->query($catalog, $this->user()->id)->findOrFail($this->route('id')) : null;
            $data = array_replace($current?->attributesToArray() ?? [], $this->validated());
            if ($catalog === 'categories') {
                $parent = isset($data['parent_id']) ? Category::forUser($this->user()->id)->find($data['parent_id']) : null;
                if ($parent && ($parent->parent_id || $parent->id === $current?->id || $parent->type->value !== $data['type'])) {
                    $validator->errors()->add('parent_id', 'Use uma categoria principal do mesmo tipo; não são permitidos ciclos.');
                }
                if ($current && (($data['type'] !== $current->type->value) || (($data['parent_id'] ?? null) != $current->parent_id))) {
                    $validator->errors()->add('type', 'Tipo e hierarquia são imutáveis. Crie outra categoria para preservar o histórico.');
                }
                $duplicate = Category::withTrashed()->forUser($this->user()->id)->where('name', $data['name'])->where('type', $data['type'])->where('parent_scope', $data['parent_id'] ?? 0);
                if ($current) {
                    $duplicate->whereKeyNot($current->id);
                }
                if ($duplicate->exists()) {
                    $validator->errors()->add('name', 'Já existe uma categoria com este nome e hierarquia, inclusive arquivada.');
                }
            }
            if ($catalog === 'merchants' && ! empty($data['subcategory_id'])) {
                $sub = Category::forUser($this->user()->id)->find($data['subcategory_id']);
                if (! $sub || ! $sub->parent_id || $sub->parent_id != ($data['category_id'] ?? null)) {
                    $validator->errors()->add('subcategory_id', 'A subcategoria deve pertencer à categoria selecionada.');
                }
            }
            if ($catalog === 'merchants' && ! empty($data['category_id']) && Category::find($data['category_id'])?->parent_id) {
                $validator->errors()->add('category_id', 'Selecione uma categoria principal.');
            }
        }];
    }
}
