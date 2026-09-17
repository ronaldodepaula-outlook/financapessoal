<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\CatalogRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CatalogService
{
    public function __construct(private CatalogRepository $repository, private AuditService $audit) {}

    public function save(User $user, string $catalog, array $data, ?int $id = null)
    {
        return DB::transaction(function () use ($user, $catalog, $data, $id) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $model = $id ? $this->repository->query($catalog, $user->id)->lockForUpdate()->findOrFail($id) : new ($this->repository->model($catalog));
            if ($catalog === 'merchants' && isset($data['name'])) {
                $data['normalized_name'] = Str::upper(trim(preg_replace('/\s+/', ' ', Str::ascii($data['name']))));
                $query = $this->repository->query($catalog, $user->id)->withTrashed()->where('normalized_name', $data['normalized_name']);
                if ($id) {
                    $query->whereKeyNot($id);
                }
                if ($query->exists()) {
                    throw ValidationException::withMessages(['name' => 'Já existe um estabelecimento com este nome normalizado.']);
                }
            }
            if ($catalog === 'accounts' && $id && array_key_exists('initial_balance', $data) && bccomp($data['initial_balance'], $model->initial_balance, 2) !== 0
                && ($model->transactions()->exists() || $model->outgoingTransfers()->exists() || $model->incomingTransfers()->exists() || $model->invoicePayments()->exists())) {
                throw ValidationException::withMessages(['initial_balance' => 'Saldo inicial não pode mudar após movimentações.']);
            }
            if ($catalog === 'categories' && $id && ($data['status'] ?? $model->status) === 'INATIVO'
                && $model->status !== 'INATIVO' && $model->children()->exists()) {
                throw ValidationException::withMessages(['status' => 'Arquive ou inative as subcategorias ativas antes de inativar esta categoria.']);
            }
            $model->fill($data);
            $model->user_id = $user->id;
            $model->save();
            $this->audit->record($user, $catalog.($id ? '.updated' : '.created'), $model, array_keys($data));

            return $model->fresh();
        });
    }

    public function archive(User $user, string $catalog, int $id): void
    {
        DB::transaction(function () use ($user, $catalog, $id) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $model = $this->repository->query($catalog, $user->id)->lockForUpdate()->findOrFail($id);
            if ($catalog === 'categories' && $model->children()->exists()) {
                abort(409);
            }
            $model->status = 'INATIVO';
            $model->save();
            $model->delete();
            $this->audit->record($user, $catalog.'.archived', $model);
        });
    }
}
