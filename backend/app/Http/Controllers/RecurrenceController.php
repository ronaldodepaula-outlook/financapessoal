<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Helpers\FinancialCalendar;
use App\Http\Requests\RecurrenceRequest;
use App\Repositories\RecurrenceRepository;
use App\Services\RecurrenceService;
use Illuminate\Http\Request;

class RecurrenceController extends Controller
{
    public function __construct(private RecurrenceRepository $repository, private RecurrenceService $service) {}

    public function index(Request $request)
    {
        $filter = $request->validate(['search' => 'sometimes|string|max:160', 'active' => 'sometimes|boolean', 'page' => 'sometimes|integer|min:1', 'per_page' => 'sometimes|integer|between:1,100']);
        $query = $this->repository->query($request->route('recurrence'), $request->user()->id);
        if (isset($filter['search'])) {
            $query->where($request->route('recurrence') === 'subscriptions' ? 'name' : 'description', 'like', '%'.$filter['search'].'%');
        }
        if (isset($filter['active'])) {
            $query->where('active', $filter['active']);
        }

        return ApiResponse::page($query->orderByDesc('id')->paginate($filter['per_page'] ?? 20));
    }

    public function show(Request $request)
    {
        return ApiResponse::success($this->repository->query($request->route('recurrence'), $request->user()->id)->findOrFail($request->route('id')));
    }

    public function store(RecurrenceRequest $request)
    {
        return ApiResponse::success($this->service->save($request->user(), $request->route('recurrence'), $request->validated()), 'Recorrência criada.', 201);
    }

    public function update(RecurrenceRequest $request)
    {
        return ApiResponse::success($this->service->save($request->user(), $request->route('recurrence'), $request->validated(), (int) $request->route('id')), 'Recorrência atualizada; lançamentos anteriores preservados.');
    }

    public function destroy(Request $request)
    {
        $this->service->archive($request->user(), $request->route('recurrence'), (int) $request->route('id'));

        return ApiResponse::success(null, 'Recorrência arquivada; previsões já geradas foram preservadas.');
    }

    public function generate(Request $request)
    {
        $data = $request->validate(['year' => 'required|integer|between:1900,2200', 'month' => 'required|integer|between:1,12', 'months' => 'sometimes|integer|between:1,24']);

        return ApiResponse::success($this->service->generate($request->user(), FinancialCalendar::month($data['year'], $data['month']), $data['months'] ?? 1), 'Previsões processadas.');
    }
}
