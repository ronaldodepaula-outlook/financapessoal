<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FixedExpense extends Model
{
    use BelongsToUser;
    use SoftDeletes;

    protected $fillable = ['category_id', 'subcategory_id', 'account_id', 'card_id', 'description', 'amount', 'due_day', 'payment_method', 'active', 'start_date', 'end_date'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'due_day' => 'integer',
            'payment_method' => PaymentMethod::class,
            'active' => 'boolean',
            'start_date' => 'immutable_date:Y-m-d',
            'end_date' => 'immutable_date:Y-m-d',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id')->withTrashed();
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'subcategory_id')->withTrashed();
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id')->withTrashed();
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class, 'card_id')->withTrashed();
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'fixed_expense_id');
    }
}
