<?php

namespace App\Services;

use App\Helpers\FinancialCalendar;
use App\Models\Budget;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class InitialPlanningService
{
    public function __construct(private InitialCatalogService $catalog, private BudgetService $budgets, private AuditService $audit) {}

    public function initialize(User $user): array
    {
        return DB::transaction(function () use ($user) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $config = config('initial-planning');
            $created = ['income_schedules' => 0, 'loans' => 0, 'budgets' => 0];
            if ($user->userPreferences()->first()?->planning_initialized_at) {
                return ['created' => $created, 'budgets_skipped' => count($config['budget_years']) * 12 * count($config['monthly_budget_targets']), 'pending_configuration' => []];
            }
            $this->catalog->initialize($user);
            $skipped = 0;
            $incomeCategory = $user->categories()->where('name', 'Renda')->whereNull('parent_id')->where('type', 'RECEITA')->where('status', 'ATIVO')->first();
            foreach ($config['income_schedules'] as $index => $data) {
                $key = 'initial-income-'.($index + 1);
                if ($user->incomeSchedules()->withTrashed()->where('template_key', $key)->exists()) {
                    continue;
                }
                $row = $user->incomeSchedules()->make([...$data, 'active' => false, 'category_id' => $incomeCategory?->id]);
                $row->template_key = $key;
                $row->save();
                $created['income_schedules']++;
            }
            $loanKey = 'initial-payroll-loan';
            if (! $user->loans()->where('template_key', $loanKey)->exists()) {
                $contract = $config['payroll_loan'];
                $category = $user->categories()->where('name', 'Financeiro')->whereNull('parent_id')->where('type', 'DESPESA')->where('status', 'ATIVO')->first();
                $subcategory = $category?->children()->where('name', 'Consignado')->where('status', 'ATIVO')->first();
                $data = array_intersect_key($contract, array_flip(['principal_amount', 'installments', 'installment_amount', 'interest_rate', 'cet', 'annual_cet', 'iof']));
                $loan = $user->loans()->make([...$data, 'name' => 'Consignado inicial', 'loan_type' => 'CONSIGNADO', 'status' => 'RASCUNHO', 'category_id' => $category?->id, 'subcategory_id' => $subcategory?->id,
                    'notes' => 'Período informado: '.$contract['reported_start_month'].' a '.$contract['reported_end_month'].'. Confirme o primeiro vencimento e o tratamento da renda antes de ativar.']);
                $loan->template_key = $loanKey;
                $loan->save();
                $created['loans']++;
            }
            foreach ($config['budget_years'] as $year) {
                for ($month = 1; $month <= 12; $month++) {
                    if (FinancialCalendar::month($year, $month)->lt(CarbonImmutable::now()->startOfMonth())) {
                        $skipped += count($config['monthly_budget_targets']);

                        continue;
                    }
                    foreach ($config['monthly_budget_targets'] as $target) {
                        $category = $user->categories()->where('name', $target['category'])->whereNull('parent_id')->where('type', 'DESPESA')->where('status', 'ATIVO')->first();
                        $sub = $target['subcategory'] ? $category?->children()->where('name', $target['subcategory'])->where('status', 'ATIVO')->first() : null;
                        if (! $category || ($target['subcategory'] && ! $sub)) {
                            $skipped++;

                            continue;
                        }
                        $query = Budget::forUser($user->id)->where('year', $year)->where('month', $month)->where('category_id', $category->id);
                        if ($sub) {
                            $query->where(fn ($q) => $q->whereNull('subcategory_id')->orWhere('subcategory_id', $sub->id));
                        }
                        if ($query->exists()) {
                            $skipped++;

                            continue;
                        }
                        $this->budgets->save($user, ['year' => $year, 'month' => $month, 'category_id' => $category->id, 'subcategory_id' => $sub?->id, 'planned_amount' => $target['planned_amount'], 'reason' => 'Planejamento inicial editável']);
                        $created['budgets']++;
                    }
                }
            }
            $preferences = $user->userPreferences()->firstOrFail();
            $preferences->planning_initialized_at = now();
            $preferences->save();
            $this->audit->record($user, 'planning.initialized', $user);

            return ['created' => $created, 'budgets_skipped' => $skipped, 'pending_configuration' => ['Dias e início das receitas; contas de recebimento.', 'Primeiro vencimento do consignado e renda líquida ou bruta.', 'Valor desejado para o orçamento de contas fixas.']];
        });
    }
}
