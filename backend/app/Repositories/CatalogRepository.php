<?php

namespace App\Repositories;

use App\Models\Account;
use App\Models\Card;
use App\Models\Category;
use App\Models\Merchant;
use Illuminate\Database\Eloquent\Builder;

class CatalogRepository
{
    public function model(string $catalog): string
    {
        return match ($catalog) {
            'accounts' => Account::class, 'cards' => Card::class,
            'categories' => Category::class, 'merchants' => Merchant::class,
            default => abort(404),
        };
    }

    public function query(string $catalog, int $userId): Builder
    {
        return $this->model($catalog)::forUser($userId);
    }
}
