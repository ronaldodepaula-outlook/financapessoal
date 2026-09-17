<?php

namespace App\Services;

use App\Helpers\FinancialCalendar;
use App\Helpers\FinancialTotals;
use App\Helpers\Money;
use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BudgetService
{
    public function __construct(private AuditService $audit) {}

    private function editable(int $year, int $month): void
    {
        if (FinancialCalendar::month($year, $month)->lt(CarbonImmutable::now()->startOfMonth())) {
            throw ValidationException::withMessages(['year' => 'Orçamentos de meses encerrados são preservados.']);
        }
    }

    public function save(User $user, array $data, ?int $id = null): Budget
    {
        return DB::transaction(function () use ($user, $data, $id) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $budget = $id ? Budget::forUser($user->id)->lockForUpdate()->findOrFail($id) : new Budget;
            $previous = $budget->planned_amount ?? '0.00';
            $reason = $data['reason'] ?? null;
            unset($data['reason']);
            $budget->fill($data);
            $budget->user_id = $user->id;
            $this->editable($budget->year, $budget->month);
            if (! $id) {
                $category = Category::forUser($user->id)->where('status', 'ATIVO')->find($budget->category_id);
                if (! $category || $category->parent_id || $category->type->value !== 'DESPESA') {
                    throw ValidationException::withMessages(['category_id' => 'Informe uma categoria principal de despesa ativa.']);
                }
                if ($budget->subcategory_id && ! Category::forUser($user->id)->where('status', 'ATIVO')->where('parent_id', $budget->category_id)->whereKey($budget->subcategory_id)->exists()) {
                    throw ValidationException::withMessages(['subcategory_id' => 'Subcategoria inválida para a categoria.']);
                }
                $overlap = Budget::forUser($user->id)->where('year', $budget->year)->where('month', $budget->month)->where('category_id', $budget->category_id);
                if ($budget->subcategory_id) {
                    $overlap->where(fn ($q) => $q->whereNull('subcategory_id')->orWhere('subcategory_id', $budget->subcategory_id));
                }
                if ($overlap->exists()) {
                    throw ValidationException::withMessages(['category_id' => 'Já existe orçamento para este escopo. Não sobreponha categoria e subcategorias.']);
                }
            }
            $budget->save();
            if (! $id || bccomp($previous, $budget->planned_amount, 2) !== 0) {
                $user->budgetRevisions()->create(['budget_id' => $budget->id, 'version' => ($budget->revisions()->max('version') ?? 0) + 1, 'previous_amount' => $previous, 'new_amount' => $budget->planned_amount, 'reason' => $reason]);
                $this->audit->record($user, 'budgets.'.($id ? 'revised' : 'created'), $budget, array_keys($data));
            }

            return $budget->fresh();
        });
    }

    public function copy(User $user, int $source, int $target, bool $overwrite = false): array
    {
        return DB::transaction(function () use ($user, $source, $target, $overwrite) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($source === $target) {
                throw ValidationException::withMessages(['target_year' => 'Escolha um ano diferente da origem.']);
            }
            $created = 0;
            $updated = 0;
            $skipped = 0;
            foreach (Budget::forUser($user->id)->where('year', $source)->orderBy('month')->get() as $row) {
                $key = ['year' => $target, 'month' => $row->month, 'category_id' => $row->category_id, 'subcategory_id' => $row->subcategory_id];
                $existing = Budget::forUser($user->id)->where($key)->first();
                if ($existing && ! $overwrite) {
                    $skipped++;

                    continue;
                }
                $this->save($user, [...($existing ? [] : $key), 'planned_amount' => $row->planned_amount, 'reason' => "Cópia do orçamento de $source"], $existing?->id);
                if ($existing) {
                    $updated++;
                } else {
                    $created++;
                }
            }

            return compact('created', 'updated', 'skipped');
        });
    }

    public function comparison(User $user, int $year, int $month): array
    {
        $budgets = Budget::forUser($user->id)->where('year', $year)->where('month', $month)->with(['category', 'subcategory'])->orderBy('category_id')->orderBy('subcategory_id')->get();
        $expenses = $user->transactions()->where('competence_year', $year)->where('competence_month', $month)->where('transaction_type', 'DESPESA')->where('status', '!=', 'CANCELADA')->get();
        $covered = [];
        $items = [];
        foreach ($budgets as $budget) {
            $rows = $expenses->filter(fn ($tx) => $tx->category_id === $budget->category_id && (! $budget->subcategory_id || $tx->subcategory_id === $budget->subcategory_id));
            foreach ($rows as $tx) {
                $covered[$tx->id] = true;
            }
            $actual = FinancialTotals::sum($rows->filter(fn ($tx) => $tx->status->value === 'PAGA'));
            $committed = FinancialTotals::sum($rows);
            $items[] = [...$budget->toArray(), 'actual_amount' => $actual, 'committed_amount' => $committed, 'remaining_amount' => Money::subtract($budget->planned_amount, $actual),
                'projected_remaining' => Money::subtract($budget->planned_amount, $committed), 'usage_percent' => FinancialTotals::percentage($actual, $budget->planned_amount)];
        }
        $actual = FinancialTotals::sum($expenses->filter(fn ($tx) => $tx->status->value === 'PAGA'));
        $planned = FinancialTotals::sum($budgets, 'planned_amount');

        return ['year' => $year, 'month' => $month, 'items' => $items, 'planned_amount' => $planned, 'actual_amount' => $actual, 'committed_amount' => FinancialTotals::sum($expenses),
            'remaining_amount' => Money::subtract($planned, $actual), 'unbudgeted_actual' => FinancialTotals::sum($expenses->filter(fn ($tx) => $tx->status->value === 'PAGA' && ! isset($covered[$tx->id])))];
    }

    public function fortnight(User $user, int $year, int $month): array
    {
        $start = FinancialCalendar::month($year, $month);
        $end = $start->endOfMonth();
        // Vencimento distribui previsões; na ausência dele usa-se a data do lançamento.
        $rows = $user->transactions()->where('status', '!=', 'CANCELADA')->whereRaw('COALESCE(due_date, transaction_date) >= ?', [$start->toDateString()])->whereRaw('COALESCE(due_date, transaction_date) <= ?', [$end->toDateString()])->get();
        $periods = [];
        foreach (['01_15' => [1, 15], '16_31' => [16, $end->day]] as $name => [$first,$last]) {
            $period = $rows->filter(fn ($tx) => ($tx->due_date ?? $tx->transaction_date)->day >= $first && ($tx->due_date ?? $tx->transaction_date)->day <= $last);
            $income = $period->filter(fn ($tx) => $tx->transaction_type->value === 'RECEITA');
            $expenses = $period->filter(fn ($tx) => $tx->transaction_type->value === 'DESPESA' && $tx->cash_flow_effect);
            $received = FinancialTotals::sum($income->filter(fn ($tx) => $tx->status->value === 'PAGA'));
            $paid = FinancialTotals::sum($expenses->filter(fn ($tx) => $tx->status->value === 'PAGA'));
            $expected = FinancialTotals::sum($income);
            $committed = FinancialTotals::sum($expenses);
            $periods[] = ['period' => $name, 'from' => $start->day($first)->toDateString(), 'to' => $start->day($last)->toDateString(), 'expected_income' => $expected, 'received_income' => $received,
                'committed_expenses' => $committed, 'paid_expenses' => $paid, 'pending_expenses' => Money::subtract($committed, $paid), 'realized_balance' => Money::subtract($received, $paid),
                'projected_balance' => Money::subtract($expected, $committed), 'available_to_spend' => Money::subtract($expected, $committed),
                'payroll_already_deducted' => FinancialTotals::sum($period->filter(fn ($tx) => ! $tx->cash_flow_effect))];
        }
        $warnings = [];
        if ($user->incomeSchedules()->where('active', false)->exists()) {
            $warnings[] = 'Existem receitas inativas que não participam da previsão.';
        }
        if ($user->userPreferences?->income_is_net_of_payroll_loan === null) {
            $warnings[] = 'Confirme se a renda já está líquida do consignado.';
        }

        return ['year' => $year, 'month' => $month, 'basis' => 'VENCIMENTO', 'periods' => $periods, 'warnings' => $warnings];
    }
}
