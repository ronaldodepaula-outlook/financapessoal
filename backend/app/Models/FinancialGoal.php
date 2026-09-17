<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinancialGoal extends Model
{
    use BelongsToUser;
    use SoftDeletes;

    protected $fillable = ['name', 'target_amount', 'target_date', 'initial_amount', 'status', 'notes'];

    protected function casts(): array
    {
        return [
            'target_amount' => 'decimal:2',
            'target_date' => 'immutable_date:Y-m-d',
            'initial_amount' => 'decimal:2',
        ];
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(GoalContribution::class, 'financial_goal_id');
    }
}
