<?php

namespace App\Repositories;

use App\Models\FixedExpense;
use App\Models\IncomeSchedule;
use App\Models\Subscription;

class RecurrenceRepository
{
    public function model(string $resource): string
    {
        return match ($resource) {
            'fixed-expenses' => FixedExpense::class,'subscriptions' => Subscription::class,'income-schedules' => IncomeSchedule::class,default => abort(404)
        };
    }

    public function query(string $resource, int $userId)
    {
        return $this->model($resource)::forUser($userId);
    }
}
