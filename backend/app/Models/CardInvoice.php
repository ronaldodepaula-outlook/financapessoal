<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CardInvoice extends Model
{
    use BelongsToUser;

    protected $fillable = ['card_id', 'competence_year', 'competence_month', 'closing_date', 'due_date', 'status'];

    protected function casts(): array
    {
        return [
            'competence_year' => 'integer',
            'competence_month' => 'integer',
            'closing_date' => 'immutable_date:Y-m-d',
            'due_date' => 'immutable_date:Y-m-d',
            'status' => PaymentStatus::class,
        ];
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class, 'card_id')->withTrashed();
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'card_invoice_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CardInvoicePayment::class, 'card_invoice_id');
    }
}
