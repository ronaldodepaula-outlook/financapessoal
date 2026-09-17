<?php

namespace App\Rules;

use App\Models\Account;
use App\Models\Card;
use App\Models\Category;
use App\Models\Merchant;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class OwnedRecord implements ValidationRule
{
    public function __construct(private string $model, private int $userId, private bool $active = true) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! filter_var($value, FILTER_VALIDATE_INT) || $value < 1) {
            $fail('Selecione um registro válido.');

            return;
        }
        $query = $this->model::forUser($this->userId)->whereKey($value);
        if ($this->active && in_array($this->model, [Account::class, Card::class, Category::class, Merchant::class])) {
            $query->where('status', 'ATIVO');
        }
        if (! $query->exists()) {
            $fail('O registro selecionado está indisponível.');
        }
    }
}
