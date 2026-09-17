<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanBalanceSnapshot extends Model
{
    use BelongsToUser;

    protected $fillable = ['loan_id', 'outstanding_balance', 'reported_at', 'notes'];

    protected function casts(): array
    {
        return [
            'outstanding_balance' => 'decimal:2',
            'reported_at' => 'immutable_date:Y-m-d',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class, 'loan_id');
    }
}
