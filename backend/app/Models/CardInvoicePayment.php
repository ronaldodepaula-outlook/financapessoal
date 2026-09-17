<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardInvoicePayment extends Model
{
    use BelongsToUser;

    protected $fillable = ['card_invoice_id', 'account_id', 'amount', 'payment_date', 'status', 'notes'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_date' => 'immutable_date:Y-m-d',
            'status' => PaymentStatus::class,
        ];
    }

    public function cardInvoice(): BelongsTo
    {
        return $this->belongsTo(CardInvoice::class, 'card_invoice_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id')->withTrashed();
    }
}
