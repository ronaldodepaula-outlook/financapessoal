<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Import extends Model
{
    use BelongsToUser;

    protected $fillable = ['original_filename', 'stored_path', 'file_hash', 'format', 'account_id', 'card_id', 'column_mapping', 'status', 'confirmed_at'];

    protected $hidden = ['stored_path'];

    protected function casts(): array
    {
        return [
            'column_mapping' => 'array',
            'confirmed_at' => 'immutable_datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id')->withTrashed();
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class, 'card_id')->withTrashed();
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class, 'import_id');
    }
}
