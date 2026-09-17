<?php

namespace App\Services;

use App\Helpers\Money;
use App\Models\Import;
use App\Models\ImportRow;
use App\Models\User;
use App\Rules\MoneyAmount;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ImportService
{
    public function __construct(private StatementParser $parser,private FinancialReferences $references,private TransactionService $transactions,private AuditService $audit){}
    private function lock(User $user):void{User::whereKey($user->id)->lockForUpdate()->firstOrFail();}
    public function upload(User $user,UploadedFile $file,array $target):Import
    {
        $format=strtoupper($file->getClientOriginalExtension());$text=$file->getContent();$rows=$this->parser->parse($text,$format);
        $path=$file->storeAs('imports/'.$user->id,Str::uuid().'.'.strtolower($format),'local');
        if(!$path)abort(503);
        try{return DB::transaction(function()use($user,$file,$format,$text,$rows,$target,$path){
            $this->lock($user);
            $record=$user->imports()->create([...$target,'format'=>$format,'original_filename'=>mb_substr(basename($file->getClientOriginalName()),0,255),'stored_path'=>$path,'file_hash'=>hash('sha256',$text)]);
            foreach($rows as$i=>$raw)$user->importRows()->create(['import_id'=>$record->id,'row_number'=>$i+1,'raw_data'=>$raw]);
            $mapping=$this->guessMapping(array_keys($rows[0]),$format,$rows[0]);
            $this->map($user,$record->id,$mapping);$this->audit->record($user,'imports.uploaded',$record);return $record->fresh();
        });}catch(\Throwable $e){Storage::disk('local')->delete($path);throw $e;}
    }
    private function guessMapping(array $columns,string $format,array $sample=[]):array
    {
        if($format==='OFX')return ['date'=>'date','description'=>'description','amount'=>'amount','date_format'=>'OFX','number_format'=>'en-US'];
        $mapping=['date_format'=>'d/m/Y','number_format'=>'pt-BR'];
        $aliases=['date'=>['data','date','data lancamento','data da transacao'],'description'=>['descricao','description','historico','memo','title'],'amount'=>['valor','amount','montante'],'type'=>['tipo','type']];
        foreach($columns as$column){$key=Str::lower(Str::ascii(trim($column)));foreach($aliases as$field=>$names)if(in_array($key,$names,true))$mapping[$field]=$column;}
        if(!empty($mapping['date'])&&isset($sample[$mapping['date']])){
            $value=trim((string)$sample[$mapping['date']]);
            if(preg_match('/^\d{4}-\d{1,2}-\d{1,2}$/',$value))$mapping['date_format']='Y-m-d';
            elseif(preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/',$value))$mapping['date_format']='d/m/Y';
        }
        if(!empty($mapping['amount'])&&isset($sample[$mapping['amount']])){
            $value=trim((string)$sample[$mapping['amount']]);
            if(preg_match('/^-?\d+\.\d{1,2}$/',$value))$mapping['number_format']='en-US';
        }
        return $mapping;
    }
    public function map(User $user,int $id,array $mapping):Import
    {
        return DB::transaction(function()use($user,$id,$mapping){
            $this->lock($user);$import=Import::forUser($user->id)->findOrFail($id);if($import->status!=='PREVIA')abort(409);
            $import->column_mapping=$mapping;$import->save();$seen=$this->existing($user,$import);
            foreach($import->rows()->orderBy('row_number')->get()as$row){
                $data=[];$errors=[];
                try{$data=$this->normalize($row->raw_data,$mapping,$import);$this->validate($user,$data);}catch(ValidationException $e){$errors=$e->errors();}
                $fingerprint=$this->canFingerprint($data)?$this->fingerprint($data):null;
                $duplicate=$fingerprint&&isset($seen[$fingerprint]);if($fingerprint)$seen[$fingerprint]=true;
                $cardCredit=$this->isCardCredit($row->raw_data,$mapping,$import);
                $looksLikePayment=preg_match('/pagamento|pgto/i',Str::ascii($data['description']??$row->raw_data[$mapping['description']??'']??''));
                $review=preg_match('/pagamento.{0,20}fatura|pgto.{0,20}fatura|transferencia entre contas/i',Str::ascii($data['description']??''))||strtoupper($row->raw_data['source_type']??'')==='XFER'||$cardCredit;
                $row->mapped_data=$data;$row->fingerprint=$fingerprint;$row->validation_errors=$errors;$row->status=$duplicate?'DUPLICADA':($errors?'INVALIDA':'PENDENTE');$row->selected=!$duplicate&&!$review&&!$errors;
                if($review)$row->validation_errors=[...$errors,'review'=>[$cardCredit?($looksLikePayment?'Pagamento recebido no cartão. Um cartão só registra despesas neste sistema; use Faturas → Pagamentos para o pagamento da fatura.':'Crédito recebido no cartão (estorno, reembolso ou outro crédito). Um cartão só registra despesas neste sistema; revise antes de confirmar.'):'Possível transferência ou pagamento de fatura. Desmarcada para evitar duplicar despesas; use o módulo correspondente.']];
                $row->save();
            }
            $this->audit->record($user,'imports.mapped',$import,array_keys($mapping));return $import->fresh();
        });
    }
    private function normalize(array $raw,array $mapping,Import $import):array
    {
        foreach(['date','description','amount']as$key)if(empty($mapping[$key])||!array_key_exists($mapping[$key],$raw))throw ValidationException::withMessages(['mapping'=>'Mapeie data, descrição e valor.']);
        $value=trim((string)$raw[$mapping['amount']]);$value=preg_replace('/^R\$\s*/','',$value);$value=str_replace(["\xC2\xA0",' '],'',$value);
        if(($mapping['number_format']??'pt-BR')==='pt-BR'){
            if(!preg_match('/^-?(?:\d+|\d{1,3}(?:\.\d{3})+)(?:,\d{1,2})?$/D',$value))throw ValidationException::withMessages(['amount'=>'Valor inválido para o formato brasileiro.']);
            $value=str_replace(',','.',str_replace('.','',$value));
        }elseif(!preg_match('/^-?(?:\d+|\d{1,3}(?:,\d{3})+)(?:\.\d{1,2})?$/D',$value))throw ValidationException::withMessages(['amount'=>'Valor inválido para o formato decimal selecionado.']);
        else $value=str_replace(',','',$value);
        $cardCsv=$import->card_id&&$import->format!=='OFX';
        $type=$mapping['transaction_type']??($cardCsv?(str_starts_with($value,'-')?'RECEITA':'DESPESA'):(str_starts_with($value,'-')?'DESPESA':'RECEITA'));
        if(!empty($mapping['type'])&&!empty($raw[$mapping['type']])){
            $explicit=match(Str::upper(Str::ascii(trim($raw[$mapping['type']])))){'DESPESA','DEBITO','SAIDA','DEBIT','WITHDRAWAL'=>'DESPESA','RECEITA','CREDITO','ENTRADA','CREDIT','DEPOSIT','DEPOSITO'=>'RECEITA',default=>null};
            if($explicit!==null)$type=$explicit;
        }
        if($import->card_id&&$type==='RECEITA')$type='DESPESA';
        $amount=ltrim($value,'-');Validator::make(['amount'=>$amount],['amount'=>['required',new MoneyAmount]])->validate();
        $date=trim((string)$raw[$mapping['date']]);$format=$mapping['date_format']??'d/m/Y';
        if($import->format==='OFX'){$date=substr($date,0,8);$format='Ymd';}
        $parsed=\DateTimeImmutable::createFromFormat('!'.$format,$date);
        if(!$parsed||$parsed->format($format)!==$date||$parsed->format('Y')<1900||$parsed->format('Y')>2200)throw ValidationException::withMessages(['transaction_date'=>'Data inválida para o formato selecionado.']);
        $category=$type==='RECEITA'?($mapping['income_category_id']??null):($mapping['expense_category_id']??null);
        return ['description'=>trim((string)$raw[$mapping['description']]),'transaction_type'=>$type,'amount'=>Money::normalize($amount),'transaction_date'=>$parsed->format('Y-m-d'),'competence_year'=>(int)$parsed->format('Y'),'competence_month'=>(int)$parsed->format('m'),
            'account_id'=>$import->account_id,'card_id'=>$import->card_id,'category_id'=>$category,'subcategory_id'=>null,'payment_method'=>$import->card_id?'CREDITO':($mapping['payment_method']??'OUTROS'),'status'=>'PAGA'];
    }
    private function isCardCredit(array $raw,array $mapping,Import $import):bool
    {
        if(!$import->card_id||empty($mapping['amount'])||!array_key_exists($mapping['amount'],$raw))return false;
        $type=$mapping['transaction_type']??null;
        if(!empty($mapping['type'])&&!empty($raw[$mapping['type']])){
            $value=Str::upper(Str::ascii(trim((string)$raw[$mapping['type']])));
            if(in_array($value,['DESPESA','DEBITO','SAIDA','DEBIT','WITHDRAWAL'],true))$type='DESPESA';
            elseif(in_array($value,['RECEITA','CREDITO','ENTRADA','CREDIT','DEPOSIT','DEPOSITO'],true))$type='RECEITA';
        }
        if($type===null){
            $amount=trim((string)$raw[$mapping['amount']]);$amount=preg_replace('/^R\$\s*/','',$amount);$amount=str_replace(["\xC2\xA0",' '],'',$amount);
            $cardCsv=$import->format!=='OFX';$isNegative=str_starts_with($amount,'-');
            $type=($cardCsv?$isNegative:!$isNegative)?'RECEITA':'DESPESA';
        }
        return $type==='RECEITA';
    }
    private function validate(User $user,array $data):void
    {
        Validator::make($data,['description'=>'required|string|max:255','amount'=>['required',new MoneyAmount],'transaction_type'=>'required|in:RECEITA,DESPESA','transaction_date'=>'required|date_format:Y-m-d','category_id'=>'required|integer|min:1','subcategory_id'=>'nullable|integer|min:1'])->validate();
        $this->references->validate($user,$data);
    }
    private function canFingerprint(array $data):bool{return isset($data['description'],$data['amount'],$data['transaction_date'],$data['transaction_type']);}
    private function fingerprint(array $data):string
    {
        $description=Str::upper(Str::ascii(trim(preg_replace('/\s+/u',' ',$data['description']))));
        return hash('sha256',implode('|',[$data['transaction_date'],Money::normalize($data['amount']),$description,$data['transaction_type'],$data['account_id']??'',$data['card_id']??'']));
    }
    private function existing(User $user,Import $import):array
    {
        $seen=[];foreach($user->transactions()->where('account_id',$import->account_id)->where('card_id',$import->card_id)->cursor()as$tx){$seen[$tx->import_fingerprint??$this->fingerprint($tx->attributesToArray())]=true;}return $seen;
    }
    private function applyRowEdit(User $user,ImportRow $row,Import $import,array $data):void
    {
        $mapped=array_replace($row->mapped_data??[],array_intersect_key($data,array_flip(['category_id','subcategory_id','description','transaction_type'])));
        if($import->card_id&&($mapped['transaction_type']??null)==='RECEITA')$mapped['transaction_type']='DESPESA';
        $errors=[];try{$this->validate($user,$mapped);}catch(ValidationException $e){$errors=$e->errors();}
        $row->mapped_data=$mapped;$row->selected=$errors?false:($data['selected']??$row->selected);$row->fingerprint=$this->canFingerprint($mapped)?$this->fingerprint($mapped):null;
        $row->status=$errors?'INVALIDA':'PENDENTE';$row->validation_errors=$errors;$row->save();
    }
    public function editRow(User $user,int $id,array $data):ImportRow
    {
        return DB::transaction(function()use($user,$id,$data){
            $this->lock($user);$row=ImportRow::forUser($user->id)->findOrFail($id);$import=$row->import;
            if(!in_array($import->status,['PREVIA','CONCLUIDA'],true))abort(409);
            if($row->status==='IMPORTADA')throw ValidationException::withMessages(['selected'=>'Esta linha já foi importada como lançamento; edite o lançamento diretamente se precisar corrigi-lo.']);
            $this->applyRowEdit($user,$row,$import,$data);
            $this->audit->record($user,'import-rows.reviewed',$row,array_keys($data));return $row;
        });
    }
    public function bulkUpdateRows(User $user,int $importId,array $updates):array
    {
        return DB::transaction(function()use($user,$importId,$updates){
            $this->lock($user);$import=Import::forUser($user->id)->findOrFail($importId);
            if(!in_array($import->status,['PREVIA','CONCLUIDA'],true))abort(409);
            $updated=0;$failed=[];
            foreach($updates as$item){
                $id=$item['id']??null;$fields=array_diff_key($item,['id'=>true]);
                $row=$id?ImportRow::forUser($user->id)->where('import_id',$importId)->find($id):null;
                if(!$row){$failed[]=['id'=>$id,'message'=>'Linha não encontrada.'];continue;}
                if($row->status==='IMPORTADA'){$failed[]=['id'=>$id,'message'=>'Já importada como lançamento; não pode ser alterada aqui.'];continue;}
                $this->applyRowEdit($user,$row,$import,$fields);$updated++;
            }
            $this->audit->record($user,'import-rows.bulk-updated',$import,['count'=>$updated]);
            return ['updated'=>$updated,'failed'=>$failed,'import'=>$this->present($import->fresh())];
        });
    }
    public function confirm(User $user,int $id):Import
    {
        return DB::transaction(function()use($user,$id){
            $this->lock($user);$import=Import::forUser($user->id)->lockForUpdate()->findOrFail($id);
            if(!in_array($import->status,['PREVIA','CONCLUIDA'],true))abort(409);
            $rows=$import->rows()->where('selected',true)->where('status','!=','IMPORTADA')->orderBy('row_number')->lockForUpdate()->get();
            if($rows->isEmpty()){
                if($import->status==='CONCLUIDA')return $import;
                throw ValidationException::withMessages(['rows'=>'Selecione pelo menos uma linha válida.']);
            }
            $seen=$this->existing($user,$import);$imported=0;
            foreach($rows as$row){
                $data=$row->mapped_data??[];
                try{$this->validate($user,$data);}catch(ValidationException $e){$row->status='INVALIDA';$row->selected=false;$row->validation_errors=$e->errors();$row->save();continue;}
                $fingerprint=$this->fingerprint($data);
                if(isset($seen[$fingerprint])){$row->status='DUPLICADA';$row->selected=false;$row->save();continue;}
                $tx=$this->transactions->save($user,$data);$tx->import_fingerprint=$fingerprint;$tx->save();
                $row->transaction_id=$tx->id;$row->fingerprint=$fingerprint;$row->status='IMPORTADA';$row->validation_errors=[];$row->save();$seen[$fingerprint]=true;$imported++;
            }
            if($imported===0){
                if($import->status==='PREVIA')throw ValidationException::withMessages(['rows'=>'Nenhuma linha selecionada pôde ser importada. Revise as linhas e tente novamente.']);
                return $import;
            }
            $import->status='CONCLUIDA';$import->confirmed_at=$import->confirmed_at??now();$import->save();$this->audit->record($user,'imports.confirmed',$import);return $import;
        });
    }
    public function present(Import $import):array
    {
        return [...$import->toArray(),'columns'=>array_keys($import->rows()->first()?->raw_data??[]),'counts'=>['total'=>$import->rows()->count(),'selected'=>$import->rows()->where('selected',true)->count(),'imported'=>$import->rows()->where('status','IMPORTADA')->count(),'duplicates'=>$import->rows()->where('status','DUPLICADA')->count(),'invalid'=>$import->rows()->where('status','INVALIDA')->count(),'left_out'=>$import->rows()->whereIn('status',['PENDENTE','DUPLICADA'])->where('selected',false)->count()]];
    }
}
