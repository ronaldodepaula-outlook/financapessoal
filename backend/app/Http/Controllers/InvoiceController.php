<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\InvoicePaymentRequest;
use App\Http\Requests\InvoiceRequest;
use App\Models\CardInvoice;
use App\Services\BalanceService;
use App\Services\InvoiceService;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function __construct(private InvoiceService $service, private BalanceService $balances) {}

    public function index(Request $request)
    {
        $filter = $request->validate(['card_id' => 'sometimes|integer|min:1', 'per_page' => 'sometimes|integer|between:1,100', 'page' => 'sometimes|integer|min:1']);
        $query = CardInvoice::forUser($request->user()->id);
        if (isset($filter['card_id'])) {
            $query->where('card_id', $filter['card_id']);
        }
        $page = $query->orderByDesc('due_date')->orderByDesc('id')->paginate($filter['per_page'] ?? 20);
        $page->getCollection()->transform(fn ($row) => [...$row->toArray(), ...$this->balances->invoice($row)]);

        return ApiResponse::page($page);
    }

    public function show(Request $request, int $id)
    {
        $invoice = CardInvoice::forUser($request->user()->id)->findOrFail($id);

        return ApiResponse::success([...$invoice->toArray(), ...$this->balances->invoice($invoice)]);
    }

    public function store(InvoiceRequest $request)
    {
        return ApiResponse::success($this->service->create($request->user(), $request->validated()), 'Fatura criada.', 201);
    }

    public function linkTransactions(Request $request, int $id)
    {
        $result = $this->service->linkTransactions($request->user(), $id);
        $invoice = $result['invoice'];

        return ApiResponse::success(['linked' => $result['linked'], ...$invoice->toArray(), ...$this->balances->invoice($invoice)], $result['linked'] ? $result['linked'].' lançamento(s) vinculado(s) à fatura.' : 'Nenhum lançamento novo para vincular.');
    }

    public function payments(Request $request, int $id)
    {
        $filter = $request->validate(['per_page' => 'sometimes|integer|between:1,100', 'page' => 'sometimes|integer|min:1']);
        $invoice = CardInvoice::forUser($request->user()->id)->findOrFail($id);

        return ApiResponse::page($invoice->payments()->orderByDesc('payment_date')->orderByDesc('id')->paginate($filter['per_page'] ?? 20));
    }

    public function pay(InvoicePaymentRequest $request, int $id)
    {
        return ApiResponse::success($this->service->pay($request->user(), $id, $request->validated()), 'Pagamento registrado sem nova despesa.', 201);
    }

    public function cancelPayment(Request $request, int $id)
    {
        $this->service->cancelPayment($request->user(), $id);

        return ApiResponse::success(null,'Pagamento cancelado; saldo restaurado.');
    }
}
