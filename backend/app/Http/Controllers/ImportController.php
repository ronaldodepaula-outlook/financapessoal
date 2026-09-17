<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\ImportUploadRequest;
use App\Models\Category;
use App\Models\Import;
use App\Models\ImportRow;
use App\Rules\OwnedRecord;
use App\Services\ImportService;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    public function __construct(private ImportService $service){}
    public function index(Request $request){$f=$request->validate(['page'=>'sometimes|integer|min:1','per_page'=>'sometimes|integer|between:1,100']);return ApiResponse::page(Import::forUser($request->user()->id)->orderByDesc('id')->paginate($f['per_page']??20)->through(fn($i)=>$this->service->present($i)));}
    public function store(ImportUploadRequest $request){return ApiResponse::success($this->service->present($this->service->upload($request->user(),$request->file('file'),$request->safe()->except('file'))),'Arquivo recebido. Revise antes de confirmar.',201);}
    public function show(Request $request,int $id){return ApiResponse::success($this->service->present(Import::forUser($request->user()->id)->findOrFail($id)));}
    public function rows(Request $request,int $id){$f=$request->validate(['page'=>'sometimes|integer|min:1','per_page'=>'sometimes|integer|between:1,100']);return ApiResponse::page(Import::forUser($request->user()->id)->findOrFail($id)->rows()->orderBy('row_number')->paginate($f['per_page']??50));}
    public function mapping(Request $request,int $id)
    {
        $import=Import::forUser($request->user()->id)->findOrFail($id);
        $owned=['nullable','integer',new OwnedRecord(Category::class,$request->user()->id)];
        $data=$request->validate(['date'=>'required|string|max:255','description'=>'required|string|max:255','amount'=>'required|string|max:255','type'=>'nullable|string|max:255','date_format'=>'required|in:Y-m-d,d/m/Y,m/d/Y,OFX','number_format'=>'required|in:pt-BR,en-US','transaction_type'=>['nullable','in:'.($import->card_id?'DESPESA':'RECEITA,DESPESA')],'expense_category_id'=>$owned,'income_category_id'=>$owned,'payment_method'=>'sometimes|in:PIX,DINHEIRO,DEBITO,TRANSFERENCIA,BOLETO,OUTROS']);
        return ApiResponse::success($this->service->present($this->service->map($request->user(),$id,$data)));
    }
    public function editRow(Request $request,int $id)
    {
        $row=ImportRow::forUser($request->user()->id)->findOrFail($id);$owned=['nullable','integer',new OwnedRecord(Category::class,$request->user()->id)];
        $data=$request->validate(['category_id'=>$owned,'subcategory_id'=>$owned,'description'=>'sometimes|string|max:255','transaction_type'=>['sometimes','in:'.($row->import->card_id?'DESPESA':'RECEITA,DESPESA')],'selected'=>'sometimes|boolean']);
        return ApiResponse::success($this->service->editRow($request->user(),$id,$data));
    }
    public function bulkUpdateRows(Request $request,int $id)
    {
        $import=Import::forUser($request->user()->id)->findOrFail($id);
        $owned=['nullable','integer',new OwnedRecord(Category::class,$request->user()->id)];
        $data=$request->validate(['updates'=>'required|array|min:1|max:200','updates.*.id'=>'required|integer','updates.*.category_id'=>$owned,'updates.*.subcategory_id'=>$owned,'updates.*.description'=>'sometimes|string|max:255','updates.*.transaction_type'=>['sometimes','in:'.($import->card_id?'DESPESA':'RECEITA,DESPESA')],'updates.*.selected'=>'sometimes|boolean']);
        return ApiResponse::success($this->service->bulkUpdateRows($request->user(),$id,$data['updates']),'Linhas atualizadas.');
    }
    public function confirm(Request $request,int $id){return ApiResponse::success($this->service->present($this->service->confirm($request->user(),$id)),'Importação concluída.');}
}
