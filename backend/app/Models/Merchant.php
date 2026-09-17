<?php

namespace App\Models;

use App\Enums\RecordStatus;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Merchant extends Model
{
    use BelongsToUser;
    use SoftDeletes;

    protected $fillable = ['name', 'normalized_name', 'category_id', 'subcategory_id', 'status'];

    protected function casts(): array
    {
        return [
            'status' => RecordStatus::class,
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

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'merchant_id');
    }
}
