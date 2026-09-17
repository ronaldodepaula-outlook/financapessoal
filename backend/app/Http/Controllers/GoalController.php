<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\GoalRequest;
use App\Models\FinancialGoal;
use App\Models\Transfer;
use App\Rules\MoneyAmount;
use App\Rules\OwnedRecord;
use App\Services\GoalService;
use Illuminate\Http\Request;

class GoalController extends Controller
{
    public function __construct(private GoalService $service) {}

    public function index(Request $request)
    {
        $data = $request->validate(['status' => 'sometimes|in:ATIVA,CONCLUIDA', 'page' => 'sometimes|integer|min:1', 'per_page' => 'sometimes|integer|between:1,100']);
        $query = FinancialGoal::forUser($request->user()->id);
        if (isset($data['status'])) {
            $query->where('status', $data['status']);
        }

        return ApiResponse::page($query->orderByDesc('id')->paginate($data['per_page'] ?? 20)->through(fn ($goal) => $this->service->present($goal)));
    }

    public function show(Request $request, int $id)
    {
        return ApiResponse::success($this->service->present(FinancialGoal::forUser($request->user()->id)->findOrFail($id)));
    }

    public function store(GoalRequest $request)
    {
        return ApiResponse::success($this->service->present($this->service->save($request->user(), $request->validated())), 'Meta criada.', 201);
    }

    public function update(GoalRequest $request, int $id)
    {
        return ApiResponse::success($this->service->present($this->service->save($request->user(), $request->validated(), $id)));
    }

    public function destroy(Request $request, int $id)
    {
        $this->service->archive($request->user(), $id);

        return ApiResponse::success(null, 'Meta arquivada; contribuições preservadas.');
    }

    public function contributions(Request $request, int $id)
    {
        $data = $request->validate(['page' => 'sometimes|integer|min:1', 'per_page' => 'sometimes|integer|between:1,100']);

        return ApiResponse::page(FinancialGoal::withTrashed()->forUser($request->user()->id)->findOrFail($id)->contributions()->orderByDesc('id')->paginate($data['per_page'] ?? 20));
    }

    public function contribute(Request $request, int $id)
    {
        FinancialGoal::forUser($request->user()->id)->findOrFail($id);
        $data = $request->validate(['amount' => ['required', new MoneyAmount], 'contribution_date' => 'required|date_format:Y-m-d', 'transfer_id' => ['nullable', 'integer', new OwnedRecord(Transfer::class, $request->user()->id)], 'notes' => 'nullable|string|max:5000', 'user_id' => 'prohibited', 'financial_goal_id' => 'prohibited', 'cancelled_at' => 'prohibited']);

        return ApiResponse::success($this->service->contribute($request->user(), $id, $data), 'Contribuição registrada.', 201);
    }

    public function cancelContribution(Request $request, int $id)
    {
        $this->service->cancelContribution($request->user(), $id);

        return ApiResponse::success(null,'Contribuição cancelada.');
    }
}
