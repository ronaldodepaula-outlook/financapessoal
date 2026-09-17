<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\CatalogRequest;
use App\Models\Account;
use App\Models\Card;
use App\Repositories\CatalogRepository;
use App\Services\BalanceService;
use App\Services\CatalogService;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function __construct(private CatalogRepository $repository, private CatalogService $service) {}

    public function index(Request $request)
    {
        $filter = $request->validate(['search' => 'sometimes|string|max:120', 'status' => 'sometimes|in:ATIVO,INATIVO', 'type' => 'sometimes|in:RECEITA,DESPESA', 'parent_id' => 'sometimes|nullable|integer|min:1', 'per_page' => 'sometimes|integer|between:1,100', 'page' => 'sometimes|integer|min:1']);
        $query = $this->repository->query($request->route('catalog'), $request->user()->id);
        if (! empty($filter['search'])) {
            $query->where('name', 'like', '%'.$filter['search'].'%');
        }
        if (! empty($filter['status'])) {
            $query->where('status', $filter['status']);
        }
        if ($request->route('catalog') === 'categories') {
            if (! empty($filter['type'])) {
                $query->where('type', $filter['type']);
            }
            if (array_key_exists('parent_id', $filter)) {
                $query->where('parent_id', $filter['parent_id']);
            }
        }
        $page = $query->orderBy('name')->orderBy('id')->paginate($filter['per_page'] ?? 20);
        $page->getCollection()->transform(fn ($record) => $this->present($record));

        return ApiResponse::page($page);
    }

    public function show(Request $request)
    {
        return ApiResponse::success($this->present($this->repository->query($request->route('catalog'), $request->user()->id)->findOrFail($request->route('id'))));
    }

    public function store(CatalogRequest $request)
    {
        return ApiResponse::success($this->service->save($request->user(), $request->route('catalog'), $request->validated()), 'Cadastro criado.', 201);
    }

    public function update(CatalogRequest $request)
    {
        return ApiResponse::success($this->service->save($request->user(), $request->route('catalog'), $request->validated(), (int) $request->route('id')), 'Cadastro atualizado.');
    }

    public function destroy(Request $request)
    {
        $this->service->archive($request->user(), $request->route('catalog'), (int) $request->route('id'));

        return ApiResponse::success(null, 'Cadastro arquivado; histórico preservado.');
    }

    private function present($record): array
    {
        $data = $record->toArray();
        if ($record instanceof Account) {
            $data['current_balance'] = app(BalanceService::class)->account($record);
        }
        if ($record instanceof Card) {
            $data = [...$data, ...app(BalanceService::class)->card($record)];
        }

        return $data;
    }
}
