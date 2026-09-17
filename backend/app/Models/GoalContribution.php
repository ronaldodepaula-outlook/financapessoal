<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoalContribution extends Model
{
    use BelongsToUser;

    protected $fillable = ['financial_goal_id', 'transfer_id', 'amount', 'contribution_date', 'notes'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'cancelled_at' => 'immutable_datetime',
            'contribution_date' => 'immutable_date:Y-m-d',
        ];
    }

    public function financialGoal(): BelongsTo
    {
        return $this->belongsTo(FinancialGoal::class, 'financial_goal_id')->withTrashed();
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class, 'transfer_id');
    }
}
