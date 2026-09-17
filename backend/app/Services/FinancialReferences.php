<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Card;
use App\Models\CardInvoice;
use App\Models\Category;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class FinancialReferences
{
    public function validate(User $user, array $data): void
    {
        $errors = [];
        foreach (['account_id' => Account::class, 'card_id' => Card::class, 'category_id' => Category::class, 'subcategory_id' => Category::class, 'merchant_id' => Merchant::class] as $field => $model) {
            if (! empty($data[$field]) && ! $model::forUser($user->id)->whereKey($data[$field])->where('status', 'ATIVO')->exists()) {
                $errors[$field] = 'O registro selecionado está indisponível.';
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        $root = Category::forUser($user->id)->find($data['category_id']);
        if (! $root || $root->parent_id || $root->type->value !== $data['transaction_type']) {
            $errors['category_id'] = 'Selecione uma categoria principal do mesmo tipo do lançamento.';
        }
        if (! empty($data['subcategory_id'])) {
            $sub = Category::forUser($user->id)->find($data['subcategory_id']);
            if (! $sub || $sub->parent_id != $data['category_id'] || $sub->type->value !== $data['transaction_type']) {
                $errors['subcategory_id'] = 'A subcategoria deve pertencer à categoria selecionada.';
            }
        }
        $credit = ($data['payment_method'] === 'CREDITO');
        if ($credit && ($data['transaction_type'] !== 'DESPESA' || empty($data['card_id']) || ! empty($data['account_id']))) {
            $errors['card_id'] = 'Compra a crédito exige cartão e não movimenta conta bancária diretamente.';
        }
        if (! $credit && (empty($data['account_id']) || ! empty($data['card_id']) || ! empty($data['card_invoice_id']))) {
            $errors['account_id'] = 'Recebimento ou despesa fora do crédito exige conta, sem cartão/fatura.';
        }
        if (! empty($data['card_invoice_id'])) {
            $invoice = CardInvoice::forUser($user->id)->find($data['card_invoice_id']);
            if (! $invoice || $invoice->card_id != ($data['card_id'] ?? null)) {
                $errors['card_invoice_id'] = 'A fatura deve pertencer ao cartão selecionado.';
            } elseif ($invoice->status->value === 'CANCELADA') {
                $errors['card_invoice_id'] = 'A fatura está cancelada.';
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }
}
