<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\LoanPaymentRequest;
use App\Http\Requests\LoanRequest;
use App\Models\Loan;
use App\Rules\MoneyAmount;
use App\Services\LoanService;
use Illuminate\Http\Request;

class LoanController extends Controller
{
    public function __construct(private LoanService $service) {}

    public function index(Request $request)
    {
        $data = $request->validate(['status' => 'sometimes|in:RASCUNHO,ATIVO,QUITADO,CANCELADO', 'page' => 'sometimes|integer|min:1', 'per_page' => 'sometimes|integer|between:1,100']);
        $query = Loan::forUser($request->user()->id);
        if (isset($data['status'])) {
            $query->where('status', $data['status']);
        }
        $page = $query->orderByDesc('id')->paginate($data['per_page'] ?? 20);
        $page->through(fn ($loan) => $this->service->present($loan));

        return ApiResponse::page($page);
    }

    public function show(Request $request, int $id)
    {
        return ApiResponse::success($this->service->present(Loan::forUser($request->user()->id)->findOrFail($id), true));
    }

    public function store(LoanRequest $request)
    {
        return ApiResponse::success($this->service->present($this->service->save($request->user(), $request->validated())), 'Contrato salvo como rascunho.', 201);
    }

    public function update(LoanRequest $request, int $id)
    {
        return ApiResponse::success($this->service->present($this->service->save($request->user(), $request->validated(), $id)));
    }

    public function activate(LoanRequest $request, int $id)
    {
        return ApiResponse::success($this->service->present($this->service->activate($request->user(), $id, $request->validated()), true), 'Cronograma ativado.');
    }

    public function destroy(Request $request, int $id)
    {
        $this->service->cancel($request->user(), $id);

        return ApiResponse::success(null, 'Contrato cancelado.');
    }

    public function payments(Request $request, int $id)
    {
        $data = $request->validate(['page' => 'sometimes|integer|min:1', 'per_page' => 'sometimes|integer|between:1,100']);

        return ApiResponse::page(Loan::forUser($request->user()->id)->findOrFail($id)->payments()->orderByDesc('id')->paginate($data['per_page'] ?? 20));
    }

    public function pay(LoanPaymentRequest $request, int $id)
    {
        return ApiResponse::success($this->service->pay($request->user(), $id, $request->validated()), 'Pagamento registrado.', 201);
    }

    public function cancelPayment(Request $request, int $id)
    {
        $this->service->cancelPayment($request->user(), $id);

        return ApiResponse::success(null, 'Pagamento cancelado.');
    }

    public function balances(Request $request, int $id)
    {
        $data = $request->validate(['page' => 'sometimes|integer|min:1', 'per_page' => 'sometimes|integer|between:1,100']);

        return ApiResponse::page(Loan::forUser($request->user()->id)->findOrFail($id)->balanceSnapshots()->orderByDesc('reported_at')->orderByDesc('id')->paginate($data['per_page'] ?? 20));
    }

    public function reportBalance(Request $request, int $id)
    {
        Loan::forUser($request->user()->id)->findOrFail($id);
        $data = $request->validate(['outstanding_balance' => ['required', new MoneyAmount(true)], 'reported_at' => 'required|date_format:Y-m-d|before_or_equal:today', 'notes' => 'nullable|string|max:5000', 'user_id' => 'prohibited', 'loan_id' => 'prohibited']);

        return ApiResponse::success($this->service->snapshot($request->user(),$id,$data),'Saldo informado registrado.',201);
    }
}
