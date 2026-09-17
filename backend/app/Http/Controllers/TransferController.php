<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\TransferRequest;
use App\Models\Transfer;
use App\Services\TransferService;
use Illuminate\Http\Request;

class TransferController extends Controller
{
    public function __construct(private TransferService $service) {}

    public function index(Request $request)
    {
        $data = $request->validate(['per_page' => 'sometimes|integer|between:1,100', 'page' => 'sometimes|integer|min:1', 'status' => 'sometimes|in:PENDENTE,PAGA,CANCELADA']);
        $query = Transfer::forUser($request->user()->id)->with(['sourceAccount', 'destinationAccount']);
        if (isset($data['status'])) {
            $query->where('status', $data['status']);
        }

        return ApiResponse::page($query->orderByDesc('transfer_date')->orderByDesc('id')->paginate($data['per_page'] ?? 20));
    }

    public function show(Request $request, int $id)
    {
        return ApiResponse::success(Transfer::forUser($request->user()->id)->findOrFail($id));
    }

    public function store(TransferRequest $request)
    {
        return ApiResponse::success($this->service->save($request->user(), $request->validated()), 'Transferência criada.', 201);
    }

    public function update(TransferRequest $request, int $id)
    {
        return ApiResponse::success($this->service->save($request->user(), $request->validated(), $id), 'Transferência atualizada.');
    }

    public function destroy(Request $request, int $id)
    {
        $this->service->save($request->user(), ['status' => 'CANCELADA'], $id);

        return ApiResponse::success(null,'Transferência cancelada.');
    }
}
