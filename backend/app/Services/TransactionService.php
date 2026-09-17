<?php

namespace App\Services;

use App\Models\CardInvoice;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransactionService
{
    public function __construct(private AuditService $audit, private FinancialReferences $references) {}

    public function save(User $user, array $data, ?int $id = null): Transaction
    {
        return DB::transaction(function () use ($user, $data, $id) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $record = $id ? Transaction::forUser($user->id)->lockForUpdate()->findOrFail($id) : new Transaction;
            if ($record->loan_id) {
                throw ValidationException::withMessages(['loan_id' => 'Registre ou cancele o pagamento pelo contrato do empréstimo.']);
            }
            if (($record->fixed_expense_id || $record->subscription_id || $record->income_schedule_id) && array_diff(array_keys($data), ['status', 'notes', 'card_invoice_id'])) {
                throw ValidationException::withMessages(['recurrence' => 'Previsões geradas preservam valor e competência. Edite a recorrência para próximas gerações; neste lançamento altere status, observações ou fatura.']);
            }
            if ($id && $record->installment_id && array_diff(array_keys($data), ['status', 'notes', 'card_invoice_id'])) {
                throw ValidationException::withMessages(['installment_id' => 'Altere somente status e observações de parcelas geradas; valores e cronograma preservam o total da compra.']);
            }
            if (! $id || array_diff(array_keys($data), ['status', 'notes'])) {
                $this->references->validate($user, array_replace($record->attributesToArray(), $data));
            }
            if (array_diff(array_keys($data), ['description', 'category_id', 'subcategory_id', 'merchant_id', 'notes', 'is_fixed'])) {
                foreach (array_unique(array_filter([$record->card_invoice_id, $data['card_invoice_id'] ?? null])) as $invoiceId) {
                    if (CardInvoice::forUser($user->id)->findOrFail($invoiceId)->payments()->where('status', 'PAGA')->exists()) {
                        abort(409);
                    }
                }
            }
            $record->fill($data);
            $record->user_id = $user->id;
            if (! $id && ! array_key_exists('card_invoice_id', $data) && $record->card_id && $record->payment_method?->value === 'CREDITO') {
                $invoice = CardInvoice::forUser($user->id)->where('card_id', $record->card_id)->where('competence_year', $record->competence_year)->where('competence_month', $record->competence_month)->first();
                if ($invoice) {
                    $record->card_invoice_id = $invoice->id;
                }
            }
            $status = $data['status'] ?? ($record->status?->value ?? 'PENDENTE');
            $record->paid_at = $status === 'PAGA' ? ($record->paid_at ?? now()) : null;
            $record->save();
            if ($record->installment_id) {
                $purchase = $record->installment;
                if ($purchase->status->value === 'CANCELADA' && $record->status->value === 'PENDENTE') {
                    abort(409);
                }
                if ($purchase->status->value !== 'CANCELADA') {
                    $purchase->status = $purchase->transactions()->where('status', 'PENDENTE')->exists() ? 'PENDENTE' : ($purchase->transactions()->where('status', 'PAGA')->exists() ? 'PAGA' : 'CANCELADA');
                    $purchase->save();
                }
            }
            $this->audit->record($user, 'transactions.'.($id ? 'updated' : 'created'), $record, array_keys($data));

            return $record->fresh();
        });
    }

    public function cancel(User $user, int $id): void
    {
        $this->save($user, ['status' => 'CANCELADA'], $id);
    }
}
