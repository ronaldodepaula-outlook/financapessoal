<?php

namespace App\Services;

use App\Helpers\FinancialTotals;
use App\Helpers\Money;
use App\Models\FinancialGoal;
use App\Models\GoalContribution;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GoalService
{
    public function __construct(private AuditService $audit) {}

    private function lock(User $user): void
    {
        User::whereKey($user->id)->lockForUpdate()->firstOrFail();
    }

    public function save(User $user, array $data, ?int $id = null): FinancialGoal
    {
        return DB::transaction(function () use ($user, $data, $id) {
            $this->lock($user);
            $goal = $id ? FinancialGoal::forUser($user->id)->findOrFail($id) : new FinancialGoal;
            if ($id && isset($data['initial_amount']) && bccomp($data['initial_amount'], $goal->initial_amount, 2) !== 0 && $goal->contributions()->exists()) {
                throw ValidationException::withMessages(['initial_amount' => 'Saldo inicial preservado após contribuições.']);
            }
            $goal->fill($data);
            $goal->user_id = $user->id;
            $goal->save();
            $this->audit->record($user, 'goals.'.($id ? 'updated' : 'created'), $goal, array_keys($data));

            return $goal->fresh();
        });
    }

    public function contribute(User $user, int $id, array $data): GoalContribution
    {
        return DB::transaction(function () use ($user, $id, $data) {
            $this->lock($user);
            $goal = FinancialGoal::forUser($user->id)->findOrFail($id);
            if ($goal->status !== 'ATIVA') {
                abort(409);
            }
            if (! empty($data['transfer_id'])) {
                $transfer = Transfer::forUser($user->id)->where('status', 'PAGA')->find($data['transfer_id']);
                if (! $transfer) {
                    throw ValidationException::withMessages(['transfer_id' => 'Vincule uma transferência paga do mesmo usuário.']);
                }
                $allocated = FinancialTotals::sum(GoalContribution::forUser($user->id)->where('transfer_id', $transfer->id)->whereNull('cancelled_at')->get());
                if (bccomp(Money::add($allocated, $data['amount']), $transfer->amount, 2) > 0) {
                    throw ValidationException::withMessages(['amount' => 'Contribuições vinculadas excedem o valor transferido.']);
                }
            }
            $row = $user->goalContributions()->create([...$data, 'financial_goal_id' => $id]);
            $this->audit->record($user, 'goal-contributions.created', $row, array_keys($data));

            return $row;
        });
    }

    public function cancelContribution(User $user, int $id): void
    {
        DB::transaction(function () use ($user, $id) {
            $this->lock($user);
            $row = GoalContribution::forUser($user->id)->findOrFail($id);
            if (! $row->cancelled_at) {
                $row->cancelled_at = now();
                $row->save();
                $this->audit->record($user, 'goal-contributions.cancelled', $row);
            }
        });
    }

    public function archive(User $user, int $id): void
    {
        DB::transaction(function () use ($user, $id) {
            $this->lock($user);
            $goal = FinancialGoal::forUser($user->id)->findOrFail($id);
            $goal->status = 'CANCELADA';
            $goal->save();
            $goal->delete();
            $this->audit->record($user, 'goals.archived', $goal);
        });
    }

    public function present(FinancialGoal $goal): array
    {
        $saved = Money::add($goal->initial_amount, FinancialTotals::sum($goal->contributions()->whereNull('cancelled_at')->get()));

        return [...$goal->toArray(), 'saved_amount' => $saved, 'remaining_amount' => bccomp($saved, $goal->target_amount, 2) >= 0 ? '0.00' : Money::subtract($goal->target_amount, $saved), 'progress_percent' => FinancialTotals::percentage($saved,$goal->target_amount), 'target_reached' => bccomp($saved,$goal->target_amount,2) >= 0];
    }
}
