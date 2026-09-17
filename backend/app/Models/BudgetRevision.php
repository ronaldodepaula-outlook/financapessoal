<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetRevision extends Model
{
    use BelongsToUser;

    protected $fillable = ['budget_id', 'version', 'previous_amount', 'new_amount', 'reason'];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'previous_amount' => 'decimal:2',
            'new_amount' => 'decimal:2',
        ];
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class, 'budget_id');
    }
}
