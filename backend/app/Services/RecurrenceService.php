<?php

namespace App\Services;

use App\Helpers\FinancialCalendar;
use App\Models\User;
use App\Repositories\RecurrenceRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecurrenceService
{
    public function __construct(private RecurrenceRepository $repository, private FinancialReferences $references, private AuditService $audit) {}

    public function validate(User $user, string $resource, array $data): void
    {
        $errors = [];
        if (! empty($data['end_date']) && ! empty($data['start_date']) && $data['end_date'] < $data['start_date']) {
            $errors['end_date'] = 'O fim deve ser igual ou posterior ao início.';
        }
        if ($resource === 'income-schedules') {
            if (! empty($data['day']) && (($data['period'] === '01_15' && $data['day'] > 15) || ($data['period'] === '16_31' && $data['day'] < 16))) {
                $errors['day'] = 'O dia deve pertencer à quinzena selecionada.';
            }
            if (! empty($data['active'])) {
                foreach (['day', 'start_date', 'account_id', 'category_id'] as $field) {
                    if (empty($data[$field])) {
                        $errors[$field] = 'Informe este campo antes de ativar a receita.';
                    }
                }
            }
            if (! $errors && ! empty($data['active'])) {
                $this->references->validate($user, [...$data, 'transaction_type' => 'RECEITA', 'payment_method' => 'OUTROS']);
            }
        } else {
            if ($resource === 'subscriptions' && $data['next_due_date'] < $data['start_date']) {
                $errors['next_due_date'] = 'O primeiro vencimento deve ser posterior ao início.';
            }
            if (! $errors && ($data['active'] ?? true)) {
                $this->references->validate($user, [...$data, 'transaction_type' => 'DESPESA']);
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    public function save(User $user, string $resource, array $data, ?int $id = null)
    {
        return DB::transaction(function () use ($user, $resource, $data, $id) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $model = $id ? $this->repository->query($resource, $user->id)->lockForUpdate()->findOrFail($id) : new ($this->repository->model($resource));
            $merged = array_replace($model->attributesToArray(), $data);
            $this->validate($user, $resource, $merged);
            if ($resource === 'subscriptions') {
                if (! $id || array_key_exists('next_due_date', $data)) {
                    $data['billing_anchor_date'] = $data['next_due_date'];
                }
                if ($id && array_key_exists('billing_cycle', $data) && $data['billing_cycle'] !== $model->billing_cycle->value && ! array_key_exists('next_due_date', $data)) {
                    throw ValidationException::withMessages(['next_due_date' => 'Informe o primeiro vencimento do novo ciclo.']);
                }
            }
            $model->fill($data);
            $model->user_id = $user->id;
            $model->save();
            $model = $model->fresh();
            if ($model->active) {
                $first = $this->firstMonth($resource, $model);
                $this->generateOne($user, $resource, $model, $first, 1);
            }
            $this->audit->record($user, $resource.($id ? '.updated' : '.created'), $model, array_keys($data));

            return $model->fresh();
        });
    }

    private function firstMonth(string $resource, $model): CarbonImmutable
    {
        $start = $resource === 'subscriptions' ? ($model->billing_anchor_date ?? $model->next_due_date) : $model->start_date;
        $month = CarbonImmutable::instance($start)->startOfMonth()->max(CarbonImmutable::now()->startOfMonth());
        if ($resource === 'subscriptions') {
            $cycle = FinancialCalendar::cycleMonths($model->billing_cycle->value);
            $distance = ($month->year - $start->year) * 12 + $month->month - $start->month;
            if ($distance % $cycle) {
                $month = $month->addMonths($cycle - ($distance % $cycle));
            }
        } elseif (FinancialCalendar::due($month, $resource === 'income-schedules' ? $model->day : $model->due_day)->lt($start)) {
            $month = $month->addMonth();
        }

        return $month;
    }

    public function generate(User $user, CarbonImmutable $from, int $months): array
    {
        if ($months < 1 || $months > 24) {
            throw ValidationException::withMessages(['months' => 'Informe entre 1 e 24 meses.']);
        }
        if ($from->startOfMonth()->addMonths($months - 1)->year > 2200) {
            throw ValidationException::withMessages(['months' => 'O período não pode ultrapassar 2200.']);
        }

        return DB::transaction(function () use ($user, $from, $months) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $count = 0;
            $warnings = [];
            foreach (['fixed-expenses', 'subscriptions', 'income-schedules'] as $resource) {
                foreach ($this->repository->query($resource, $user->id)->where('active', true)->get() as $model) {
                    try {
                        $this->validate($user, $resource, $model->attributesToArray());
                        $count += $this->generateOne($user, $resource, $model, $from, $months);
                    } catch (ValidationException $e) {
                        $warnings[] = ['resource' => $resource, 'id' => $model->id, 'errors' => $e->errors()];
                    }
                }
            }

            return ['created' => $count, 'warnings' => $warnings];
        });
    }

    private function generateOne(User $user, string $resource, $model, CarbonImmutable $from, int $months): int
    {
        $count = 0;
        $key = match ($resource) {
            'fixed-expenses' => 'fixed_expense_id','subscriptions' => 'subscription_id','income-schedules' => 'income_schedule_id'
        };
        for ($i = 0; $i < $months; $i++) {
            $month = $from->startOfMonth()->addMonths($i);
            if ($month->year > 2200) {
                throw ValidationException::withMessages(['months' => 'O período não pode ultrapassar 2200.']);
            }
            if ($resource === 'subscriptions') {
                $anchor = $model->billing_anchor_date ?? $model->next_due_date;
                $distance = ($month->year - $anchor->year) * 12 + $month->month - $anchor->month;
                if ($distance < 0 || $distance % FinancialCalendar::cycleMonths($model->billing_cycle->value)) {
                    continue;
                }
                $due = FinancialCalendar::due($month, $anchor->day);
            } else {
                $due = FinancialCalendar::due($month, $resource === 'income-schedules' ? $model->day : $model->due_day);
            }
            if ($due->lt($model->start_date) || ($model->end_date && $due->gt($model->end_date))) {
                continue;
            }
            $identity = [$key => $model->id, 'competence_year' => $due->year, 'competence_month' => $due->month];
            if ($user->transactions()->where($identity)->exists()) {
                continue;
            }
            $user->transactions()->create([
                ...$identity, 'description' => $resource === 'subscriptions' ? $model->name : $model->description,
                'transaction_type' => $resource === 'income-schedules' ? 'RECEITA' : 'DESPESA', 'category_id' => $model->category_id,
                'subcategory_id' => $resource === 'income-schedules' ? null : $model->subcategory_id, 'account_id' => $model->account_id,
                'card_id' => $resource === 'income-schedules' ? null : $model->card_id, 'transaction_date' => $due->toDateString(), 'due_date' => $due->toDateString(),
                'amount' => $model->amount, 'payment_method' => $resource === 'income-schedules' ? 'OUTROS' : $model->payment_method->value,
                'is_fixed' => $resource !== 'income-schedules', 'status' => 'PENDENTE',
            ]);
            $count++;
        }
        if ($resource === 'subscriptions') {
            $anchor = $model->billing_anchor_date ?? $model->next_due_date;
            $cycle = FinancialCalendar::cycleMonths($model->billing_cycle->value);
            $next = $anchor;
            // Próxima cobrança ainda não materializada; a âncora conserva o dia 29/30/31.
            for ($step = 0; $step <= 3612; $step++) {
                $next = $anchor->addMonthsNoOverflow($step * $cycle);
                if (! $model->transactions()->where('competence_year', $next->year)->where('competence_month', $next->month)->exists()) {
                    break;
                }
            }
            $model->next_due_date = $next;
            $model->save();
        }
        if ($count) {
            $this->audit->record($user, $resource.'.forecasts-generated', $model, [$key]);
        }

        return $count;
    }

    public function archive(User $user, string $resource, int $id): void
    {
        DB::transaction(function () use ($user, $resource, $id) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $model = $this->repository->query($resource, $user->id)->findOrFail($id);
            $model->active = false;
            $model->save();
            $model->delete();
            $this->audit->record($user,$resource.'.archived',$model);
        });
    }
}
