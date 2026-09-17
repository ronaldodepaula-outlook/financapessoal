<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transfer extends Model
{
    use BelongsToUser;

    protected $fillable = ['source_account_id', 'destination_account_id', 'amount', 'transfer_date', 'description', 'status', 'notes'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'transfer_date' => 'immutable_date:Y-m-d',
            'status' => PaymentStatus::class,
        ];
    }

    public function sourceAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'source_account_id')->withTrashed();
    }

    public function destinationAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'destination_account_id')->withTrashed();
    }
}
