<?php

namespace App\Services;

use App\Helpers\Money;
use App\Models\Account;
use App\Models\Card;
use App\Models\CardInvoice;

class BalanceService
{
    // Iteração por cursor mantém decimais exatos também no SQLite usado pelos testes.
    // Não usa floats nem mantém uma segunda fonte de verdade para o saldo.
    private function sum($query): string
    {
        $sum = '0.00';
        foreach ($query->select('id', 'amount')->cursor() as $row) {
            $sum = Money::add($sum, $row->amount);
        }

        return $sum;
    }

    public function account(Account $account): string
    {
        $balance = $account->initial_balance;
        $balance = Money::add($balance, $this->sum($account->transactions()->where('status', 'PAGA')->where('transaction_type', 'RECEITA')->whereNull('card_id')));
        $balance = Money::subtract($balance, $this->sum($account->transactions()->where('status', 'PAGA')->where('transaction_type', 'DESPESA')->whereNull('card_id')));
        $balance = Money::add($balance, $this->sum($account->incomingTransfers()->where('status', 'PAGA')));
        $balance = Money::subtract($balance, $this->sum($account->outgoingTransfers()->where('status', 'PAGA')));

        return Money::subtract($balance, $this->sum($account->invoicePayments()->where('status', 'PAGA')));
    }

    public function invoice(CardInvoice $invoice): array
    {
        $total = $this->sum($invoice->transactions()->where('status', '!=', 'CANCELADA'));
        $paid = $this->sum($invoice->payments()->where('status', 'PAGA'));

        return ['total_amount' => $total, 'paid_amount' => $paid, 'outstanding_amount' => Money::subtract($total, $paid)];
    }

    public function card(Card $card): array
    {
        $purchases = $this->sum($card->transactions()->where('status', '!=', 'CANCELADA'));
        $payments = '0.00';
        foreach ($card->invoices()->cursor() as $invoice) {
            $payments = Money::add($payments, $this->sum($invoice->payments()->where('status', 'PAGA')));
        }
        $used = Money::subtract($purchases, $payments);

        return ['used_limit' => $used, 'available_limit' => Money::subtract($card->credit_limit,$used)];
    }
}
