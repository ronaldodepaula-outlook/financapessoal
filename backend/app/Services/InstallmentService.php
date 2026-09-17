<?php

namespace App\Services;

use App\Helpers\Money;
use App\Models\Installment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class InstallmentService
{
    public function __construct(private AuditService $audit, private FinancialReferences $references) {}

    public function create(User $user, array $data): Installment
    {
        return DB::transaction(function () use ($user, $data) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->references->validate($user, [...$data, 'transaction_type' => 'DESPESA']);
            $values = Money::split($data['total_amount'], (int) $data['total_installments']);
            $start = CarbonImmutable::parse($data['start_date']);
            $purchase = $user->installments()->create([
                ...array_intersect_key($data, array_flip(['description', 'total_amount', 'total_installments', 'start_date', 'account_id', 'card_id', 'category_id', 'subcategory_id'])),
                'installment_amount' => bcdiv($data['total_amount'], (string) $data['total_installments'], 2),
                'end_date' => $start->addMonthsNoOverflow(count($values) - 1)->toDateString(),
                'status' => 'PENDENTE',
            ]);
            foreach ($values as $index => $value) {
                $due = $start->addMonthsNoOverflow($index);
                $user->transactions()->create([
                    'account_id' => $data['account_id'] ?? null, 'card_id' => $data['card_id'] ?? null,
                    'category_id' => $data['category_id'], 'subcategory_id' => $data['subcategory_id'] ?? null,
                    'transaction_type' => 'DESPESA', 'description' => $data['description'],
                    'transaction_date' => $due->toDateString(), 'due_date' => $due->toDateString(),
                    'competence_year' => $due->year, 'competence_month' => $due->month, 'amount' => $value,
                    'payment_method' => $data['payment_method'], 'is_installment' => true, 'installment_id' => $purchase->id,
                    'installment_number' => $index + 1, 'status' => 'PENDENTE', 'notes' => $data['notes'] ?? null,
                ]);
            }
            $this->audit->record($user, 'installments.created', $purchase, array_keys($data));

            return $purchase->fresh();
        });
    }

    public function cancel(User $user, int $id): void
    {
        DB::transaction(function () use ($user, $id) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $purchase = Installment::forUser($user->id)->lockForUpdate()->findOrFail($id);
            $pending = $purchase->transactions()->where('status', 'PENDENTE')->get();
            foreach ($pending as $row) {
                if ($row->card_invoice_id && $row->cardInvoice->payments()->where('status', 'PAGA')->exists()) {
                    abort(409);
                }
            }
            foreach ($pending as $row) {
                $row->status = 'CANCELADA';
                $row->save();
            }
            $purchase->status = 'CANCELADA';
            $purchase->save();
            $this->audit->record($user, 'installments.cancelled', $purchase, ['status']);
        });
    }

    public function present(Installment $purchase, bool $includeSchedule = false): array
    {
        $rows = $purchase->transactions()->orderBy('installment_number')->get();
        $pending = $rows->filter(fn ($row) => $row->status->value === 'PENDENTE');
        $balance = '0.00';
        foreach ($pending as $row) {
            $balance = Money::add($balance, $row->amount);
        }
        $result = [...$purchase->toArray(),
            'paid_installments' => $rows->filter(fn ($row) => $row->status->value === 'PAGA')->count(),
            'remaining_installments' => $pending->count(),
            'current_installment' => $pending->first()?->installment_number,
            'next_due_date' => $pending->first()?->due_date?->toDateString(),
            'outstanding_balance' => $balance,
        ];
        if ($includeSchedule) {
            $result['schedule'] = $rows;
        }

        return $result;
    }
}
