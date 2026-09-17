<?php

namespace App\Services;

use App\Helpers\Money;
use App\Models\CardInvoice;
use App\Models\CardInvoicePayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    public function __construct(private BalanceService $balances, private AuditService $audit) {}

    public function create(User $user, array $data): CardInvoice
    {
        return DB::transaction(function () use ($user, $data) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($user->cardInvoices()->where('card_id', $data['card_id'])->where('competence_year', $data['competence_year'])->where('competence_month', $data['competence_month'])->exists()) {
                throw ValidationException::withMessages(['competence_month' => 'Já existe fatura para este cartão e competência.']);
            }
            $invoice = $user->cardInvoices()->create($data);
            $user->transactions()->where('card_id', $data['card_id'])->where('competence_year', $data['competence_year'])->where('competence_month', $data['competence_month'])->whereNull('card_invoice_id')->where('status', '!=', 'CANCELADA')->update(['card_invoice_id' => $invoice->id]);
            $this->audit->record($user, 'invoices.created', $invoice, array_keys($data));

            return $invoice->fresh();
        });
    }

    public function linkTransactions(User $user, int $id): array
    {
        return DB::transaction(function () use ($user, $id) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $invoice = CardInvoice::forUser($user->id)->lockForUpdate()->findOrFail($id);
            $linked = $user->transactions()->where('card_id', $invoice->card_id)->where('competence_year', $invoice->competence_year)->where('competence_month', $invoice->competence_month)->whereNull('card_invoice_id')->where('status', '!=', 'CANCELADA')->update(['card_invoice_id' => $invoice->id]);
            if ($linked) {
                $this->audit->record($user, 'invoices.transactions-linked', $invoice, ['linked' => $linked]);
            }

            return ['linked' => $linked, 'invoice' => $invoice->fresh()];
        });
    }

    public function pay(User $user, int $id, array $data): CardInvoicePayment
    {
        return DB::transaction(function () use ($user, $id, $data) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $invoice = CardInvoice::forUser($user->id)->lockForUpdate()->findOrFail($id);
            if ($invoice->status->value === 'CANCELADA') {
                abort(409);
            }
            $balance = $this->balances->invoice($invoice);
            if (bccomp($data['amount'], $balance['outstanding_amount'], 2) > 0) {
                throw ValidationException::withMessages(['amount' => 'O pagamento supera o saldo em aberto da fatura.']);
            }
            $payment = $user->cardInvoicePayments()->create([...$data, 'card_invoice_id' => $invoice->id, 'status' => 'PAGA']);
            $remaining = Money::subtract($balance['outstanding_amount'], $data['amount']);
            $invoice->status = $remaining === '0.00' ? 'PAGA' : 'PENDENTE';
            $invoice->save();
            // Compra e liquidação permanecem fatos distintos. Não criar nova despesa.
            $this->audit->record($user, 'invoice-payments.created', $payment, array_keys($data));

            return $payment->fresh();
        });
    }

    public function cancelPayment(User $user, int $id): void
    {
        DB::transaction(function () use ($user, $id) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $payment = CardInvoicePayment::forUser($user->id)->lockForUpdate()->findOrFail($id);
            if ($payment->status->value === 'CANCELADA') {
                return;
            }
            $payment->status = 'CANCELADA';
            $payment->save();
            $invoice = $payment->cardInvoice;
            $invoice->status = 'PENDENTE';
            $invoice->save();
            $this->audit->record($user,'invoice-payments.cancelled',$payment,['status']);
        });
    }
}
