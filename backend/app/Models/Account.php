<?php

namespace App\Models;

use App\Enums\AccountType;
use App\Enums\RecordStatus;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends Model
{
    use BelongsToUser;
    use SoftDeletes;

    protected $fillable = ['name', 'institution', 'account_type', 'initial_balance', 'status'];

    protected function casts(): array
    {
        return [
            'account_type' => AccountType::class,
            'initial_balance' => 'decimal:2',
            'status' => RecordStatus::class,
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'account_id');
    }

    public function outgoingTransfers(): HasMany
    {
        return $this->hasMany(Transfer::class, 'source_account_id');
    }

    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(Transfer::class, 'destination_account_id');
    }

    public function invoicePayments(): HasMany
    {
        return $this->hasMany(CardInvoicePayment::class, 'account_id');
    }
}
