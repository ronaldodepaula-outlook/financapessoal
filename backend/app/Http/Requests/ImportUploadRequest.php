<?php

namespace App\Http\Requests;

use App\Models\Account;
use App\Models\Card;
use App\Rules\OwnedRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ImportUploadRequest extends FormRequest
{
    public function authorize():bool{return (bool)$this->user();}
    public function rules():array{return ['file'=>'required|file|max:2048','account_id'=>['nullable','integer',new OwnedRecord(Account::class,$this->user()->id)],'card_id'=>['nullable','integer',new OwnedRecord(Card::class,$this->user()->id)],'user_id'=>'prohibited'];}
    public function after():array{return [function(Validator $v){if((bool)$this->input('account_id')===(bool)$this->input('card_id'))$v->errors()->add('account_id','Escolha uma conta ou um cartão, exclusivamente.');if($this->file('file')&&!in_array(strtolower($this->file('file')->getClientOriginalExtension()),['csv','ofx']))$v->errors()->add('file','Envie arquivo CSV ou OFX.');}];}
}
