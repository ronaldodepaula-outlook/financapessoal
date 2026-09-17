<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class MoneyAmount implements ValidationRule
{
    public function __construct(private bool $allowZero = false, private bool $allowNegative = false) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // A API recebe dinheiro como string decimal ou inteiro, nunca ponto flutuante.
        if ((! is_string($value) && ! is_int($value)) || ! preg_match('/^-?\d{1,13}(\.\d{1,2})?$/D', (string) $value)) {
            $fail('Informe valor decimal como texto, com ponto e até duas casas decimais.');

            return;
        }
        $sign = bccomp((string) $value, '0', 2);
        if ((! $this->allowZero && $sign === 0) || (! $this->allowNegative && $sign < 0)) {
            $fail('O valor deve ser positivo.');
        }
    }
}
