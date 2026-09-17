<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use BelongsToUser;

    protected $fillable = ['action', 'entity_type', 'entity_id', 'changes', 'request_id'];

    protected function casts(): array
    {
        return [
            'entity_id' => 'integer',
            'changes' => 'array',
        ];
    }
}
