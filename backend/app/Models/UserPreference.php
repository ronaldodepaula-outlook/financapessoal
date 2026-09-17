<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class UserPreference extends Model
{
    use BelongsToUser;

    protected $fillable = ['income_is_net_of_payroll_loan', 'timezone', 'currency'];

    protected function casts(): array
    {
        return [
            'income_is_net_of_payroll_loan' => 'boolean',
            'planning_initialized_at' => 'immutable_datetime',
        ];
    }
}
