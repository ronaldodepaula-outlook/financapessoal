<?php

namespace App\Models;

use App\Enums\RecordStatus;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Card extends Model
{
    use BelongsToUser;
    use SoftDeletes;

    protected $fillable = ['name', 'institution', 'brand', 'last_digits', 'credit_limit', 'closing_day', 'due_day', 'status'];

    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:2',
            'closing_day' => 'integer',
            'due_day' => 'integer',
            'status' => RecordStatus::class,
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'card_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(CardInvoice::class, 'card_id');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class, 'card_id');
    }
}
