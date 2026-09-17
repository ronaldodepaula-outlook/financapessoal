<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\InstallmentRequest;
use App\Models\Installment;
use App\Services\InstallmentService;
use Illuminate\Http\Request;

class InstallmentController extends Controller
{
    public function __construct(private InstallmentService $service) {}

    public function index(Request $request)
    {
        $filter = $request->validate(['per_page' => 'sometimes|integer|between:1,100', 'page' => 'sometimes|integer|min:1', 'status' => 'sometimes|in:PENDENTE,PAGA,CANCELADA']);
        $query = Installment::forUser($request->user()->id);
        if (isset($filter['status'])) {
            $query->where('status', $filter['status']);
        }
        $page = $query->orderByDesc('start_date')->orderByDesc('id')->paginate($filter['per_page'] ?? 20);
        $page->getCollection()->transform(fn ($row) => $this->service->present($row));

        return ApiResponse::page($page);
    }

    public function show(Request $request, int $id)
    {
        return ApiResponse::success($this->service->present(Installment::forUser($request->user()->id)->findOrFail($id), true));
    }

    public function store(InstallmentRequest $request)
    {
        return ApiResponse::success($this->service->present($this->service->create($request->user(), $request->validated()), true), 'Parcelamento e parcelas criados.', 201);
    }

    public function destroy(Request $request, int $id)
    {
        $this->service->cancel($request->user(), $id);

        return ApiResponse::success(null, 'Parcelas pendentes canceladas; parcelas pagas preservadas.');
    }
}
