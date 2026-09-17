<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportRow extends Model
{
    use BelongsToUser;

    protected $fillable = ['import_id', 'transaction_id', 'row_number', 'raw_data', 'mapped_data', 'fingerprint', 'status', 'validation_errors'];

    protected function casts(): array
    {
        return [
            'row_number' => 'integer',
            'selected' => 'boolean',
            'raw_data' => 'array',
            'mapped_data' => 'array',
            'validation_errors' => 'array',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class, 'import_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }
}
