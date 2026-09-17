<?php

namespace App\Services;

use App\Helpers\Money;
use App\Models\Account;
use App\Models\Category;
use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LoanService
{
    public function __construct(private AuditService $audit) {}

    private function lock(User $user): void
    {
        User::whereKey($user->id)->lockForUpdate()->firstOrFail();
    }

    public function save(User $user, array $data, ?int $id = null): Loan
    {
        return DB::transaction(function () use ($user, $data, $id) {
            $this->lock($user);
            $loan = $id ? Loan::forUser($user->id)->lockForUpdate()->findOrFail($id) : new Loan;
            if ($id && $loan->status->value !== 'RASCUNHO' && array_diff(array_keys($data), ['name', 'institution', 'notes'])) {
                throw ValidationException::withMessages(['status' => 'Condições de contratos ativados são imutáveis para preservar o cronograma.']);
            }
            $loan->fill($data);
            $loan->user_id = $user->id;
            if ($loan->start_date && $loan->first_due_date && $loan->first_due_date->lt($loan->start_date)) {
                throw ValidationException::withMessages(['first_due_date' => 'O primeiro vencimento não pode preceder a contratação.']);
            }
            $loan->save();
            $this->audit->record($user, 'loans.'.($id ? 'updated' : 'created'), $loan, array_keys($data));

            return $loan->fresh();
        });
    }

    private function references(User $user, Loan $loan): void
    {
        $errors = [];
        $category = Category::forUser($user->id)->where('status', 'ATIVO')->find($loan->category_id);
        if (! $category || $category->parent_id || $category->type->value !== 'DESPESA') {
            $errors['category_id'] = 'Escolha uma categoria principal de despesa ativa.';
        }
        if ($loan->subcategory_id) {
            $sub = Category::forUser($user->id)->where('status', 'ATIVO')->find($loan->subcategory_id);
            if (! $sub || $sub->parent_id != $loan->category_id) {
                $errors['subcategory_id'] = 'A subcategoria deve pertencer à categoria.';
            }
        }
        if ($loan->cash_flow_mode === 'CONTA') {
            if (! Account::forUser($user->id)->where('status', 'ATIVO')->whereKey($loan->account_id)->exists()) {
                $errors['account_id'] = 'Informe a conta usada para os pagamentos.';
            }
        } elseif ($loan->cash_flow_mode === 'FOLHA') {
            if ($loan->loan_type->value !== 'CONSIGNADO' || $loan->account_id || $user->userPreferences?->income_is_net_of_payroll_loan !== true) {
                $errors['cash_flow_mode'] = 'FOLHA exige consignado, renda líquida confirmada e nenhuma conta para a dedução já realizada.';
            }
        } else {
            $errors['cash_flow_mode'] = 'Defina CONTA ou FOLHA antes de ativar.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    public function activate(User $user, int $id, array $data): Loan
    {
        return DB::transaction(function () use ($user, $id, $data) {
            $this->lock($user);
            $loan = Loan::forUser($user->id)->lockForUpdate()->findOrFail($id);
            if ($loan->status->value === 'ATIVO' && $data === []) {
                return $loan;
            }
            if ($loan->status->value !== 'RASCUNHO') {
                abort(409);
            }
            $loan = $this->save($user, $data, $id);
            if (! $loan->first_due_date) {
                throw ValidationException::withMessages(['first_due_date' => 'Confirme a data do primeiro vencimento.']);
            }
            $this->references($user, $loan);
            $end = $loan->first_due_date->addMonthsNoOverflow($loan->installments - 1);
            if ($loan->end_date && ! $loan->end_date->isSameDay($end)) {
                throw ValidationException::withMessages(['end_date' => 'A data final não corresponde ao primeiro vencimento e número de parcelas.']);
            }
            for ($i = 0; $i < $loan->installments; $i++) {
                $due = $loan->first_due_date->addMonthsNoOverflow($i);
                $tx = $this->transaction($user, $loan, $loan->installment_amount, $due->toDateString(), 'PENDENTE', $loan->account_id, $loan->cash_flow_mode === 'CONTA');
                $user->loanInstallments()->create(['loan_id' => $loan->id, 'transaction_id' => $tx->id, 'number' => $i + 1, 'due_date' => $due->toDateString(), 'amount' => $loan->installment_amount, 'status' => 'PENDENTE']);
            }
            $loan->status = 'ATIVO';
            $loan->end_date = $end;
            $loan->save();
            $this->audit->record($user, 'loans.activated', $loan);

            return $loan->fresh();
        });
    }

    private function transaction(User $user, Loan $loan, string $amount, string $date, string $status, ?int $accountId, bool $cash): Transaction
    {
        $day = CarbonImmutable::parse($date);
        $tx = $user->transactions()->make(['description' => $loan->name, 'transaction_type' => 'DESPESA', 'category_id' => $loan->category_id, 'subcategory_id' => $loan->subcategory_id,
            'account_id' => $accountId, 'transaction_date' => $date, 'due_date' => $date, 'competence_year' => $day->year, 'competence_month' => $day->month,
            'amount' => $amount, 'payment_method' => 'OUTROS', 'status' => $status, 'is_fixed' => true, 'paid_at' => $status === 'PAGA' ? $day : null]);
        $tx->loan_id = $loan->id;
        $tx->cash_flow_effect = $cash;
        $tx->save();

        return $tx;
    }

    public function pay(User $user, int $id, array $data): LoanPayment
    {
        return DB::transaction(function () use ($user, $id, $data) {
            $this->lock($user);
            $loan = Loan::forUser($user->id)->lockForUpdate()->findOrFail($id);
            if ($loan->status->value !== 'ATIVO') {
                abort(409);
            }
            $installment = null;
            if ($data['payment_type'] === 'PARCELA') {
                $installment = $loan->schedule()->where('id', $data['loan_installment_id'])->lockForUpdate()->first();
                if (! $installment || $installment->status->value !== 'PENDENTE') {
                    throw ValidationException::withMessages(['loan_installment_id' => 'Selecione uma parcela pendente deste contrato.']);
                }
                if (bccomp($data['amount'], $installment->amount, 2) !== 0) {
                    throw ValidationException::withMessages(['amount' => 'Informe o valor integral da parcela. Pagamento parcial de parcela não é suportado.']);
                }
                if ($loan->cash_flow_mode === 'FOLHA' && ! empty($data['account_id'])) {
                    throw ValidationException::withMessages(['account_id' => 'A dedução em folha já foi retirada da renda líquida.']);
                }
                $accountId = $loan->cash_flow_mode === 'FOLHA' ? null : ($data['account_id'] ?? $loan->account_id);
                $tx = $installment->transaction;
                $tx->account_id = $accountId;
                $tx->status = 'PAGA';
                $tx->paid_at = CarbonImmutable::parse($data['payment_date']);
                $tx->save();
                $installment->status = 'PAGA';
                $installment->paid_at = $tx->paid_at;
                $installment->save();
            } else {
                $accountId = $data['account_id'] ?? $loan->account_id;
                if (! $accountId) {
                    throw ValidationException::withMessages(['account_id' => 'Amortização e quitação exigem a conta de onde saiu o valor.']);
                }
                $tx = $this->transaction($user, $loan, $data['amount'], $data['payment_date'], 'PAGA', $accountId, true);
            }
            if ($accountId && ! Account::forUser($user->id)->whereKey($accountId)->where('status', 'ATIVO')->exists()) {
                throw ValidationException::withMessages(['account_id' => 'Conta indisponível.']);
            }
            $payment = $user->loanPayments()->create([...$data, 'loan_id' => $loan->id, 'account_id' => $accountId, 'transaction_id' => $tx->id, 'status' => 'PAGA']);
            if ($data['payment_type'] === 'QUITACAO') {
                foreach ($loan->schedule()->where('status', 'PENDENTE')->get() as $row) {
                    $row->transaction->update(['status' => 'CANCELADA']);
                    $row->status = 'CANCELADA';
                    $row->settled_by_payment_id = $payment->id;
                    $row->save();
                }
                $loan->status = 'QUITADO';
                $loan->save();
            } elseif ($installment && ! $loan->schedule()->where('status', 'PENDENTE')->exists()) {
                $loan->status = 'QUITADO';
                $loan->save();
            }
            $this->audit->record($user, 'loan-payments.created', $payment, array_keys($data));

            return $payment->fresh();
        });
    }

    public function cancelPayment(User $user, int $id): void
    {
        DB::transaction(function () use ($user, $id) {
            $this->lock($user);
            $payment = LoanPayment::forUser($user->id)->lockForUpdate()->findOrFail($id);
            if ($payment->status->value === 'CANCELADA') {
                return;
            }
            $loan = $payment->loan;
            if ($loan->payments()->where('status', 'PAGA')->where('id', '>', $id)->exists()) {
                abort(409);
            }
            $tx = Transaction::forUser($user->id)->findOrFail($payment->transaction_id);
            $tx->status = $payment->payment_type->value === 'PARCELA' ? 'PENDENTE' : 'CANCELADA';
            $tx->paid_at = null;
            $tx->save();
            if ($payment->loan_installment_id) {
                $row = $payment->loanInstallment;
                $row->status = 'PENDENTE';
                $row->paid_at = null;
                $row->save();
            }
            foreach ($loan->schedule()->where('settled_by_payment_id', $payment->id)->get() as $row) {
                $row->status = 'PENDENTE';
                $row->settled_by_payment_id = null;
                $row->save();
                $row->transaction->update(['status' => 'PENDENTE']);
            }
            $payment->status = 'CANCELADA';
            $payment->save();
            $loan->status = 'ATIVO';
            $loan->save();
            $this->audit->record($user, 'loan-payments.cancelled', $payment);
        });
    }

    public function cancel(User $user, int $id): void
    {
        DB::transaction(function () use ($user, $id) {
            $this->lock($user);
            $loan = Loan::forUser($user->id)->findOrFail($id);
            if ($loan->payments()->where('status', 'PAGA')->exists()) {
                abort(409);
            }
            foreach ($loan->schedule()->where('status', 'PENDENTE')->get() as $row) {
                $row->transaction->update(['status' => 'CANCELADA']);
                $row->status = 'CANCELADA';
                $row->save();
            }
            $loan->status = 'CANCELADO';
            $loan->save();
            $this->audit->record($user, 'loans.cancelled', $loan);
        });
    }

    public function snapshot(User $user, int $id, array $data)
    {
        return DB::transaction(function () use ($user, $id, $data) {
            $this->lock($user);
            Loan::forUser($user->id)->findOrFail($id);
            $row = $user->loanBalanceSnapshots()->create([...$data, 'loan_id' => $id]);
            $this->audit->record($user, 'loan-balances.reported', $row);

            return $row;
        });
    }

    public function present(Loan $loan, bool $detail = false): array
    {
        $schedule = $loan->schedule()->orderBy('number')->get();
        $pending = $schedule->filter(fn ($r) => $r->status->value === 'PENDENTE');
        $payable = '0.00';
        foreach ($pending as $row) {
            $payable = Money::add($payable, $row->amount);
        }
        $snapshot = $loan->balanceSnapshots()->orderByDesc('reported_at')->orderByDesc('id')->first();
        $data = [...$loan->toArray(), 'paid_installments' => $schedule->filter(fn ($r) => $r->status->value === 'PAGA')->count(), 'remaining_installments' => $pending->count(),
            'scheduled_payables' => $payable, 'outstanding_balance' => $loan->status->value === 'QUITADO' ? '0.00' : $snapshot?->outstanding_balance,
            'balance_reported_at' => $snapshot?->reported_at?->toDateString(), 'balance_source' => $loan->status->value === 'QUITADO' ? 'QUITACAO' : ($snapshot ? 'INFORMADO' : 'NAO_INFORMADO')];
        if ($detail) {
            $data['schedule'] = $schedule;
        }

return $data;
    }
}
