<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportRequest extends FormRequest
{
    public function authorize():bool{return (bool)$this->user();}
    public function rules():array
    {
        return ['year'=>'sometimes|integer|between:1900,2200','month'=>'sometimes|integer|between:1,12',
            'date_from'=>'sometimes|date_format:Y-m-d|after_or_equal:1900-01-01|before_or_equal:2200-12-31',
            'date_to'=>['sometimes','date_format:Y-m-d','before_or_equal:2200-12-31',Rule::when($this->filled('date_from'),'after_or_equal:date_from')],
            'category_id'=>'sometimes|integer|min:1','subcategory_id'=>'sometimes|integer|min:1','account_id'=>'sometimes|integer|min:1','card_id'=>'sometimes|integer|min:1','merchant_id'=>'sometimes|integer|min:1',
            'payment_method'=>'sometimes|in:PIX,DINHEIRO,DEBITO,CREDITO,TRANSFERENCIA,BOLETO,OUTROS','transaction_type'=>'sometimes|in:RECEITA,DESPESA','status'=>'sometimes|in:PAGA,PENDENTE',
            'is_fixed'=>'sometimes|boolean','group_by'=>'sometimes|in:category,subcategory,account,card,merchant,month,year,fixed',
            'page'=>'sometimes|integer|min:1','per_page'=>'sometimes|integer|between:1,100'];
    }
}
