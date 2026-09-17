<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\BudgetRequest;
use App\Models\Budget;
use App\Services\BudgetService;
use Illuminate\Http\Request;

class BudgetController extends Controller
{
    public function __construct(private BudgetService $service) {}

    public function index(Request $request)
    {
        $data = $request->validate(['year' => 'sometimes|integer|between:1900,2200', 'month' => 'sometimes|integer|between:1,12', 'page' => 'sometimes|integer|min:1', 'per_page' => 'sometimes|integer|between:1,100']);
        $query = Budget::forUser($request->user()->id)->with(['category', 'subcategory']);
        foreach (['year', 'month'] as $field) {
            if (isset($data[$field])) {
                $query->where($field, $data[$field]);
            }
        }

        return ApiResponse::page($query->orderBy('year')->orderBy('month')->orderBy('id')->paginate($data['per_page'] ?? 20));
    }

    public function store(BudgetRequest $request)
    {
        return ApiResponse::success($this->service->save($request->user(), $request->validated()), 'Orçamento criado.', 201);
    }

    public function show(Request $request, int $id)
    {
        return ApiResponse::success(Budget::forUser($request->user()->id)->with(['category', 'subcategory', 'revisions'])->findOrFail($id));
    }

    public function update(BudgetRequest $request, int $id)
    {
        return ApiResponse::success($this->service->save($request->user(), $request->validated(), $id), 'Revisão registrada.');
    }

    public function copy(Request $request)
    {
        $data = $request->validate(['source_year' => 'required|integer|between:1900,2200', 'target_year' => 'required|integer|between:1900,2200', 'overwrite' => 'sometimes|boolean']);

        return ApiResponse::success($this->service->copy($request->user(), $data['source_year'], $data['target_year'], $data['overwrite'] ?? false));
    }

    public function comparison(Request $request)
    {
        $data = $this->period($request);

        return ApiResponse::success($this->service->comparison($request->user(), $data['year'], $data['month']));
    }

    public function fortnight(Request $request)
    {
        $data = $this->period($request);

        return ApiResponse::success($this->service->fortnight($request->user(), $data['year'], $data['month']));
    }

    private function period(Request $request): array
    {
        return $request->validate(['year' => 'required|integer|between:1900,2200', 'month' => 'required|integer|between:1,12']);
    }
}
