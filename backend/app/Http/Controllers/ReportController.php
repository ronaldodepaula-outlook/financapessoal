<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\ReportRequest;
use App\Repositories\ReportRepository;
use App\Services\ReportService;

class ReportController extends Controller
{
    public function dashboard(ReportRequest $request,ReportService $service){$f=$request->validated();return ApiResponse::success($service->dashboard($request->user(),$f['year']??now()->year,$f['month']??now()->month));}
    public function summary(ReportRequest $request,ReportService $service){return ApiResponse::success($service->report($request->user(),$request->validated()));}
    public function transactions(ReportRequest $request,ReportRepository $repository)
    {
        $f=$request->validated();return ApiResponse::page($repository->query($request->user(),$f)->with(['category','subcategory','account','card','merchant'])->orderByDesc('transaction_date')->orderByDesc('id')->paginate($f['per_page']??20));
    }
    public function export(ReportRequest $request,ReportRepository $repository)
    {
        $query=$repository->query($request->user(),$request->validated())->with(['category','subcategory','account','card','merchant'])->orderBy('id');
        return response()->streamDownload(function()use($query){
            $out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");
            fputcsv($out,['Data','Vencimento','Competência','Descrição','Tipo','Categoria','Subcategoria','Conta','Cartão','Estabelecimento','Forma','Valor','Status','Efeito na renda'], ';', '"', '');
            foreach($query->lazyById(500)as$row){
                $cells=[$row->transaction_date->toDateString(),$row->due_date?->toDateString(),sprintf('%04d-%02d',$row->competence_year,$row->competence_month),$row->description,$row->transaction_type->value,$row->category?->name,$row->subcategory?->name,$row->account?->name,$row->card?->name,$row->merchant?->name,$row->payment_method->value,str_replace('.',',',$row->amount),$row->status->value,$row->cash_flow_effect?'Sim':'Já abatido em folha'];
                // Texto de planilha não pode ser interpretado como fórmula.
                $cells=array_map(fn($v)=>preg_match('/^[\s]*[=+\-@\t\r]/u',(string)$v)?"'".$v:(string)$v,$cells);
                fputcsv($out,$cells,';','"','');
            }
            fclose($out);
        },'relatorio-financeiro.csv',['Content-Type'=>'text/csv; charset=UTF-8','Cache-Control'=>'no-store']);
    }
}
