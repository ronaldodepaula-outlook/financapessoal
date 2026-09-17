<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\TransactionRequest;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TransactionController extends Controller
{
    public function __construct(private TransactionService $service) {}

    public function index(Request $request)
    {
        $filter = $request->validate([
            'search' => 'sometimes|string|max:255', 'date_from' => 'sometimes|date_format:Y-m-d',
            'date_to' => ['sometimes', 'date_format:Y-m-d', Rule::when($request->filled('date_from'), 'after_or_equal:date_from')],
            'category_id' => 'sometimes|integer|min:1', 'account_id' => 'sometimes|integer|min:1', 'card_id' => 'sometimes|integer|min:1', 'merchant_id' => 'sometimes|integer|min:1',
            'transaction_type' => 'sometimes|in:RECEITA,DESPESA', 'status' => 'sometimes|in:PENDENTE,PAGA,CANCELADA',
            'competence_year' => 'sometimes|integer|between:1900,2200', 'competence_month' => 'sometimes|integer|between:1,12',
            'per_page' => 'sometimes|integer|between:1,100', 'page' => 'sometimes|integer|min:1',
        ]);
        $query = Transaction::forUser($request->user()->id)->with(['category', 'subcategory', 'account', 'card', 'merchant']);
        foreach (array_intersect_key($filter, array_flip(['category_id', 'account_id', 'card_id', 'merchant_id', 'transaction_type', 'status', 'competence_year', 'competence_month'])) as $key => $value) {
            $query->where($key, $value);
        }
        if (isset($filter['search'])) {
            $query->where('description', 'like', '%'.$filter['search'].'%');
        }
        if (isset($filter['date_from'])) {
            $query->whereDate('transaction_date', '>=', $filter['date_from']);
        }
        if (isset($filter['date_to'])) {
            $query->whereDate('transaction_date', '<=', $filter['date_to']);
        }

        return ApiResponse::page($query->orderByDesc('transaction_date')->orderByDesc('id')->paginate($filter['per_page'] ?? 20));
    }

    public function show(Request $request, int $id)
    {
        return ApiResponse::success(Transaction::forUser($request->user()->id)->with(['category', 'subcategory', 'account', 'card', 'merchant'])->findOrFail($id));
    }

    public function store(TransactionRequest $request)
    {
        return ApiResponse::success($this->service->save($request->user(), $request->validated()), 'Lançamento criado.', 201);
    }

    public function update(TransactionRequest $request, int $id)
    {
        return ApiResponse::success($this->service->save($request->user(), $request->validated(), $id), 'Lançamento atualizado.');
    }

    public function destroy(Request $request, int $id)
    {
        $this->service->cancel($request->user(), $id);

        return ApiResponse::success(null, 'Lançamento cancelado; histórico preservado.');
    }
}
