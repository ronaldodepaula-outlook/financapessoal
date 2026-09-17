<?php

namespace App\Models;

use App\Enums\FortnightPeriod;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class IncomeSchedule extends Model
{
    use BelongsToUser;
    use SoftDeletes;

    protected $fillable = ['description', 'amount', 'day', 'period', 'account_id', 'category_id', 'active', 'start_date', 'end_date'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'day' => 'integer',
            'period' => FortnightPeriod::class,
            'active' => 'boolean',
            'start_date' => 'immutable_date:Y-m-d',
            'end_date' => 'immutable_date:Y-m-d',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id')->withTrashed();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id')->withTrashed();
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'income_schedule_id');
    }
}
