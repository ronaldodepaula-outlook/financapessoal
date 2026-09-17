<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use RefreshDatabase,AuthenticatesApi;
    private function fixture():array
    {
        Storage::fake('local');$user=User::factory()->create();$account=$user->accounts()->create(['name'=>'Conta','account_type'=>'CARTEIRA','initial_balance'=>'1000.00']);$expense=$user->categories()->create(['name'=>'Compras','type'=>'DESPESA']);$income=$user->categories()->create(['name'=>'Renda','type'=>'RECEITA']);
        return [$user,$this->apiHeaders($user),$account,$expense,$income];
    }
    private function upload(array $headers,int $account,string $content,string $name='extrato.csv'):array{return $this->post('/api/imports',['account_id'=>$account,'file'=>UploadedFile::fake()->createWithContent($name,$content)],$headers)->assertCreated()->json('data');}
    private function mapping(int $expense,int $income):array{return ['date'=>'Data','description'=>'Descrição','amount'=>'Valor','date_format'=>'d/m/Y','number_format'=>'pt-BR','expense_category_id'=>$expense,'income_category_id'=>$income];}
    public function test_csv_preview_mapping_confirmation_and_reimport_are_idempotent():void
    {
        [$user,$headers,$account,$expense,$income]=$this->fixture();$csv="Data;Descrição;Valor\n10/01/2027;Mercado;-120,10\n15/01/2027;Salário;1.000,00\n10/01/2027;Mercado;-120,10\n";
        $import=$this->upload($headers,$account->id,$csv);$this->assertSame(0,$user->transactions()->count());$this->assertArrayNotHasKey('stored_path',$import);
        $this->putJson('/api/imports/'.$import['id'].'/mapping',$this->mapping($expense->id,$income->id),$headers)->assertOk()->assertJsonPath('data.counts.duplicates',1);
        $this->postJson('/api/imports/'.$import['id'].'/confirm',[],$headers)->assertOk()->assertJsonPath('data.counts.imported',2);
        $this->postJson('/api/imports/'.$import['id'].'/confirm',[],$headers)->assertOk();$this->assertSame(2,$user->transactions()->count());
        $this->getJson('/api/accounts/'.$account->id,$headers)->assertJsonPath('data.current_balance','1879.90');
        $again=$this->upload($headers,$account->id,$csv);$this->putJson('/api/imports/'.$again['id'].'/mapping',$this->mapping($expense->id,$income->id),$headers)->assertJsonPath('data.counts.duplicates',3);
        $this->postJson('/api/imports/'.$again['id'].'/confirm',[],$headers)->assertUnprocessable();$this->assertSame(2,$user->transactions()->count());
    }
    public function test_invalid_rows_roll_back_all_writes_and_foreign_ids_are_rejected():void
    {
        [$user,$headers,$account,$expense,$income]=$this->fixture();$import=$this->upload($headers,$account->id,"Data;Descrição;Valor\n01/01/2027;Válida;-1,00\n31/02/2027;Inválida;-2,00\n");
        $mapping=$this->putJson('/api/imports/'.$import['id'].'/mapping',$this->mapping($expense->id,$income->id),$headers)->assertOk()->json('data');
        $this->assertSame(1,$mapping['counts']['invalid']);$this->assertSame(1,$mapping['counts']['selected']);
        $otherHeaders=$this->apiHeaders(User::factory()->create());$this->getJson('/api/imports/'.$import['id'],$otherHeaders)->assertNotFound();$this->postJson('/api/imports/'.$import['id'].'/confirm',[],$otherHeaders)->assertNotFound();
        $expense->update(['status'=>'INATIVO']);
        $this->postJson('/api/imports/'.$import['id'].'/confirm',[],$headers)->assertUnprocessable();$this->assertSame(0,$user->transactions()->count());
        $expense->update(['status'=>'ATIVO']);
        $this->postJson('/api/imports/'.$import['id'].'/confirm',[],$headers)->assertOk();$this->assertSame(1,$user->transactions()->count());
    }
    public function test_card_import_treats_purchases_as_expenses_and_excludes_card_credits():void
    {
        [$user,$headers,,$expense]=$this->fixture();$card=$user->cards()->create(['name'=>'Cartão','credit_limit'=>'1000.00','closing_day'=>10,'due_day'=>20]);
        $csv="date,title,amount\n2027-01-10,Compra Mercado,\"50,00\"\n2027-01-11,Pagamento recebido,\"- 100,00\"\n";
        $import=$this->post('/api/imports',['card_id'=>$card->id,'file'=>UploadedFile::fake()->createWithContent('nubank.csv',$csv)],$headers)->assertCreated()->json('data');
        $mapping=$this->putJson('/api/imports/'.$import['id'].'/mapping',['date'=>'date','description'=>'title','amount'=>'amount','date_format'=>'Y-m-d','number_format'=>'pt-BR','expense_category_id'=>$expense->id],$headers)->assertOk()->json('data');
        $this->assertSame(0,$mapping['counts']['invalid']);$this->assertSame(1,$mapping['counts']['selected']);
        $rows=$this->getJson('/api/imports/'.$import['id'].'/rows',$headers)->json('data.items');
        $purchase=collect($rows)->firstWhere('row_number',1);$credit=collect($rows)->firstWhere('row_number',2);
        $this->assertSame('DESPESA',$purchase['mapped_data']['transaction_type']);$this->assertTrue($purchase['selected']);
        $this->assertSame('DESPESA',$credit['mapped_data']['transaction_type']);$this->assertFalse($credit['selected']);
        $this->assertArrayHasKey('review',$credit['validation_errors']);
        $this->postJson('/api/imports/'.$import['id'].'/confirm',[],$headers)->assertOk()->assertJsonPath('data.counts.imported',1);
        $this->assertSame(1,$user->transactions()->count());$this->assertSame('DESPESA',$user->transactions()->first()->transaction_type->value);
    }
    public function test_card_ofx_import_keeps_debit_credit_convention_and_excludes_card_credits():void
    {
        [$user,$headers,,$expense]=$this->fixture();$card=$user->cards()->create(['name'=>'Cartão','credit_limit'=>'1000.00','closing_day'=>10,'due_day'=>20]);
        $ofx="OFXHEADER:100\n<OFX><BANKMSGSRSV1><STMTTRN><TRNTYPE>DEBIT<DTPOSTED>20270110<TRNAMT>-89.90<FITID>1<MEMO>Compra Mercado</STMTTRN><STMTTRN><TRNTYPE>CREDIT<DTPOSTED>20270115<TRNAMT>250.00<FITID>2<MEMO>Pagamento recebido</STMTTRN></BANKMSGSRSV1></OFX>";
        $import=$this->post('/api/imports',['card_id'=>$card->id,'file'=>UploadedFile::fake()->createWithContent('nubank.ofx',$ofx)],$headers)->assertCreated()->json('data');
        $mapping=$this->putJson('/api/imports/'.$import['id'].'/mapping',['date'=>'date','description'=>'description','amount'=>'amount','date_format'=>'OFX','number_format'=>'en-US','expense_category_id'=>$expense->id],$headers)->assertOk()->json('data');
        $this->assertSame(0,$mapping['counts']['invalid']);$this->assertSame(1,$mapping['counts']['selected']);
        $rows=$this->getJson('/api/imports/'.$import['id'].'/rows',$headers)->json('data.items');
        $purchase=collect($rows)->firstWhere('row_number',1);$credit=collect($rows)->firstWhere('row_number',2);
        $this->assertSame('DESPESA',$purchase['mapped_data']['transaction_type']);$this->assertTrue($purchase['selected']);
        $this->assertSame('DESPESA',$credit['mapped_data']['transaction_type']);$this->assertFalse($credit['selected']);
        $this->assertArrayHasKey('review',$credit['validation_errors']);
        $this->postJson('/api/imports/'.$import['id'].'/confirm',[],$headers)->assertOk()->assertJsonPath('data.counts.imported',1);
        $this->assertSame(1,$user->transactions()->count());$this->assertSame('89.90',$user->transactions()->first()->amount);
    }
    public function test_card_mapping_rejects_forced_receita_override():void
    {
        [$user,$headers,,$expense]=$this->fixture();$card=$user->cards()->create(['name'=>'Cartão','credit_limit'=>'1000.00','closing_day'=>10,'due_day'=>20]);
        $csv="date,title,amount\n2027-01-10,Compra Mercado,\"50,00\"\n";
        $import=$this->post('/api/imports',['card_id'=>$card->id,'file'=>UploadedFile::fake()->createWithContent('nubank.csv',$csv)],$headers)->assertCreated()->json('data');
        $this->putJson('/api/imports/'.$import['id'].'/mapping',['date'=>'date','description'=>'title','amount'=>'amount','date_format'=>'Y-m-d','number_format'=>'pt-BR','transaction_type'=>'RECEITA','expense_category_id'=>$expense->id],$headers)->assertUnprocessable();
    }
    public function test_bulk_row_update_applies_categories_and_selection_in_one_call():void
    {
        [$user,$headers,$account,$expense,$income]=$this->fixture();$other=$user->categories()->create(['name'=>'Lazer','type'=>'DESPESA']);
        $csv="Data;Descrição;Valor\n10/01/2027;Mercado;-120,10\n11/01/2027;Cinema;-40,00\n15/01/2027;Salário;1.000,00\n";
        $import=$this->upload($headers,$account->id,$csv);
        $this->putJson('/api/imports/'.$import['id'].'/mapping',$this->mapping($expense->id,$income->id),$headers)->assertOk();
        $rows=$this->getJson('/api/imports/'.$import['id'].'/rows',$headers)->json('data.items');
        $cinema=collect($rows)->firstWhere('row_number',2);$salario=collect($rows)->firstWhere('row_number',3);
        $result=$this->putJson('/api/imports/'.$import['id'].'/rows',['updates'=>[['id'=>$cinema['id'],'category_id'=>$other->id],['id'=>$salario['id'],'selected'=>false]]],$headers)->assertOk()->json('data');
        $this->assertSame(2,$result['updated']);$this->assertSame([],$result['failed']);
        $this->assertSame($other->id,$user->importRows()->find($cinema['id'])->mapped_data['category_id']);
        $this->assertFalse($user->importRows()->find($salario['id'])->selected);
        $this->postJson('/api/imports/'.$import['id'].'/confirm',[],$headers)->assertOk()->assertJsonPath('data.counts.imported',2);
        $this->assertSame(2,$user->transactions()->count());$this->assertSame(0,$user->transactions()->where('transaction_type','RECEITA')->count());
        $otherUser=$this->apiHeaders(User::factory()->create());
        $this->putJson('/api/imports/'.$import['id'].'/rows',['updates'=>[['id'=>$cinema['id'],'selected'=>false]]],$otherUser)->assertNotFound();
    }
    public function test_confirmed_import_can_be_reopened_to_import_previously_unselected_rows():void
    {
        [$user,$headers,$account,$expense,$income]=$this->fixture();
        $csv="Data;Descrição;Valor\n10/01/2027;Mercado;-120,10\n11/01/2027;Farmacia;-30,00\n12/01/2027;Padaria;-15,00\n";
        $import=$this->upload($headers,$account->id,$csv);
        $this->putJson('/api/imports/'.$import['id'].'/mapping',$this->mapping($expense->id,$income->id),$headers)->assertOk();
        $rows=$this->getJson('/api/imports/'.$import['id'].'/rows',$headers)->json('data.items');
        $mercado=collect($rows)->firstWhere('row_number',1);$farmacia=collect($rows)->firstWhere('row_number',2);$padaria=collect($rows)->firstWhere('row_number',3);
        $this->putJson('/api/imports/'.$import['id'].'/rows',['updates'=>[['id'=>$farmacia['id'],'selected'=>false],['id'=>$padaria['id'],'selected'=>false]]],$headers)->assertOk();
        $confirmed=$this->postJson('/api/imports/'.$import['id'].'/confirm',[],$headers)->assertOk()->json('data');
        $this->assertSame('CONCLUIDA',$confirmed['status']);$this->assertSame(1,$confirmed['counts']['imported']);$this->assertSame(2,$confirmed['counts']['left_out']);
        $this->assertSame(1,$user->transactions()->count());
        $this->putJson('/api/import-rows/'.$mercado['id'],['selected'=>true],$headers)->assertUnprocessable();
        $this->putJson('/api/import-rows/'.$farmacia['id'],['selected'=>true],$headers)->assertOk();
        $second=$this->postJson('/api/imports/'.$import['id'].'/confirm',[],$headers)->assertOk()->json('data');
        $this->assertSame(2,$second['counts']['imported']);$this->assertSame(1,$second['counts']['left_out']);
        $this->assertSame(2,$user->transactions()->count());
        $bulk=$this->putJson('/api/imports/'.$import['id'].'/rows',['updates'=>[['id'=>$mercado['id'],'selected'=>false],['id'=>$padaria['id'],'selected'=>true]]],$headers)->assertOk()->json('data');
        $this->assertSame(1,$bulk['updated']);$this->assertCount(1,$bulk['failed']);$this->assertSame($mercado['id'],$bulk['failed'][0]['id']);
        $this->postJson('/api/imports/'.$import['id'].'/confirm',[],$headers)->assertOk()->assertJsonPath('data.counts.imported',3)->assertJsonPath('data.counts.left_out',0);
        $this->assertSame(3,$user->transactions()->count());
        $this->postJson('/api/imports/'.$import['id'].'/confirm',[],$headers)->assertOk()->assertJsonPath('data.counts.imported',3);
        $this->assertSame(3,$user->transactions()->count());
    }
    public function test_one_stale_invalid_row_does_not_block_valid_rows_in_the_same_confirm():void
    {
        [$user,$headers,$account,$expense,$income]=$this->fixture();$other=$user->categories()->create(['name'=>'Saúde','type'=>'DESPESA']);
        $csv="Data;Descrição;Valor\n10/01/2027;Mercado;-120,10\n11/01/2027;Farmacia;-30,00\n";
        $import=$this->upload($headers,$account->id,$csv);
        $this->putJson('/api/imports/'.$import['id'].'/mapping',$this->mapping($expense->id,$income->id),$headers)->assertOk();
        $rows=$this->getJson('/api/imports/'.$import['id'].'/rows',$headers)->json('data.items');
        $mercado=collect($rows)->firstWhere('row_number',1);$farmacia=collect($rows)->firstWhere('row_number',2);
        $this->putJson('/api/import-rows/'.$farmacia['id'],['category_id'=>$other->id],$headers)->assertOk();
        $expense->update(['status'=>'INATIVO']);
        $result=$this->postJson('/api/imports/'.$import['id'].'/confirm',[],$headers)->assertOk()->json('data');
        $this->assertSame(1,$result['counts']['imported']);$this->assertSame(1,$result['counts']['invalid']);
        $this->assertSame(1,$user->transactions()->count());$this->assertSame('Farmacia',$user->transactions()->first()->description);
        $mercadoRow=$user->importRows()->find($mercado['id']);
        $this->assertSame('INVALIDA',$mercadoRow->status);$this->assertFalse($mercadoRow->selected);
        $expense->update(['status'=>'ATIVO']);
        $this->putJson('/api/import-rows/'.$mercado['id'],['selected'=>true],$headers)->assertOk();
        $this->postJson('/api/imports/'.$import['id'].'/confirm',[],$headers)->assertOk()->assertJsonPath('data.counts.imported',2);
        $this->assertSame(2,$user->transactions()->count());
    }
    public function test_ofx_and_duplicate_of_manual_transaction():void
    {
        [$user,$headers,$account,$expense,$income]=$this->fixture();
        $user->transactions()->create(['description'=>'Mercado','transaction_type'=>'DESPESA','amount'=>'10.00','transaction_date'=>'2027-01-10','competence_year'=>2027,'competence_month'=>1,'account_id'=>$account->id,'category_id'=>$expense->id,'payment_method'=>'PIX','status'=>'PAGA']);
        $ofx="OFXHEADER:100\n<OFX><BANKMSGSRSV1><STMTTRN><TRNTYPE>DEBIT<DTPOSTED>20270110120000[-3:BRT]<TRNAMT>-10.00<FITID>123<MEMO>MERCADO</STMTTRN><STMTTRN><TRNTYPE>CREDIT<DTPOSTED>20270115<TRNAMT>100.00<FITID>124<MEMO>Salário</STMTTRN></BANKMSGSRSV1></OFX>";
        $import=$this->upload($headers,$account->id,$ofx,'extrato.ofx');$this->putJson('/api/imports/'.$import['id'].'/mapping',['date'=>'date','description'=>'description','amount'=>'amount','date_format'=>'OFX','number_format'=>'en-US','expense_category_id'=>$expense->id,'income_category_id'=>$income->id],$headers)->assertJsonPath('data.counts.duplicates',1);
        $this->postJson('/api/imports/'.$import['id'].'/confirm',[],$headers)->assertOk()->assertJsonPath('data.counts.imported',1);$this->assertSame(2,$user->transactions()->count());
        $this->post('/api/imports',['account_id'=>$account->id,'file'=>UploadedFile::fake()->createWithContent('bad.ofx','<!DOCTYPE x [<!ENTITY foo SYSTEM "file:///secret">]><OFX></OFX>')],$headers)->assertUnprocessable();
    }
}
