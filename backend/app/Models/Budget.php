<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Budget extends Model
{
    use BelongsToUser;

    protected $fillable = ['year', 'month', 'category_id', 'subcategory_id', 'planned_amount'];

    protected $hidden = ['subcategory_scope'];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'planned_amount' => 'decimal:2',
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

    public function revisions(): HasMany
    {
        return $this->hasMany(BudgetRevision::class, 'budget_id');
    }
}
