<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class InitialCatalogService
{
    public function initialize(User $user): void
    {
        DB::transaction(function () use ($user) {
            $groups = require database_path('seeders/data/categories.php');
            foreach ($groups as $type => $categories) {
                foreach ($categories as $name => $children) {
                    $root = $user->categories()->withTrashed()->firstOrCreate(['name' => $name, 'type' => $type, 'parent_id' => null]);
                    foreach ($children as $child) {
                        $user->categories()->withTrashed()->firstOrCreate(['name' => $child, 'type' => $type, 'parent_id' => $root->id]);
                    }
                }
            }
            $user->userPreferences()->firstOrCreate([], ['timezone' => 'America/Sao_Paulo', 'currency' => 'BRL', 'income_is_net_of_payroll_loan' => null]);
        });
    }
}
