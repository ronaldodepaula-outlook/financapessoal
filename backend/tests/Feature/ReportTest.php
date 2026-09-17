<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase,AuthenticatesApi;
    public function test_dashboard_aggregates_competence_without_counting_cancelled_records_or_other_users():void
    {
        $user=User::factory()->create();$headers=$this->apiHeaders($user);$category=$user->categories()->create(['name'=>'Compras','type'=>'DESPESA']);$account=$user->accounts()->create(['name'=>'Conta','account_type'=>'CARTEIRA']);
        $base=['description'=>'Compra','category_id'=>$category->id,'account_id'=>$account->id,'transaction_type'=>'DESPESA','transaction_date'=>'2027-02-01','competence_year'=>2027,'competence_month'=>1,'payment_method'=>'PIX'];
        $user->transactions()->create([...$base,'amount'=>'10.10','status'=>'PAGA','is_fixed'=>true]);
        $user->transactions()->create([...$base,'amount'=>'20.20','status'=>'PENDENTE']);
        $user->transactions()->create([...$base,'amount'=>'99.00','status'=>'CANCELADA']);
        $user->transactions()->create([...$base,'amount'=>'40.40','status'=>'PAGA','competence_month'=>2]);
        $income=$user->categories()->create(['name'=>'Renda','type'=>'RECEITA']);$user->transactions()->create([...$base,'category_id'=>$income->id,'transaction_type'=>'RECEITA','amount'=>'100.00','status'=>'PAGA']);
        $this->getJson('/api/dashboard?year=2027&month=1',$headers)->assertOk()->assertJsonPath('data.summary.expenses','10.10')->assertJsonPath('data.summary.fixed_expenses','10.10')->assertJsonPath('data.summary.balance','89.90')->assertJsonPath('data.summary.projected_balance','69.70')->assertJsonCount(12,'data.monthly_evolution');
        $this->getJson('/api/dashboard?year=2027&month=1',$this->apiHeaders(User::factory()->create()))->assertJsonPath('data.summary.expenses','0.00');
        $this->getJson('/api/reports/summary?date_from=2027-02-01&date_to=2027-02-28&group_by=category',$headers)->assertOk()->assertJsonPath('data.totals.expenses','50.50');
        $this->getJson('/api/reports/summary?date_from=2027-02-28&date_to=2027-02-01',$headers)->assertUnprocessable();
    }
    public function test_dashboard_breaks_down_categories_by_subcategory():void
    {
        $user=User::factory()->create();$headers=$this->apiHeaders($user);$category=$user->categories()->create(['name'=>'Compras','type'=>'DESPESA']);$sub=$user->categories()->create(['name'=>'Mercado','type'=>'DESPESA','parent_id'=>$category->id]);$account=$user->accounts()->create(['name'=>'Conta','account_type'=>'CARTEIRA']);
        $base=['account_id'=>$account->id,'transaction_type'=>'DESPESA','transaction_date'=>'2027-03-01','competence_year'=>2027,'competence_month'=>3,'payment_method'=>'PIX','status'=>'PAGA','category_id'=>$category->id];
        $user->transactions()->create([...$base,'description'=>'Feira','subcategory_id'=>$sub->id,'amount'=>'30.00']);
        $user->transactions()->create([...$base,'description'=>'Sem subcategoria','amount'=>'15.00']);
        $response=$this->getJson('/api/dashboard?year=2027&month=3',$headers)->assertOk()->assertJsonPath('data.by_category.0.label','Compras')->assertJsonPath('data.by_category.0.expenses','45.00');
        $subs=collect($response->json('data.by_category.0.subcategories'));
        $this->assertSame('30.00',$subs->firstWhere('label','Mercado')['expenses']);
        $this->assertSame('15.00',$subs->firstWhere('label','Sem subcategoria')['expenses']);
    }
    public function test_csv_export_filters_and_escapes_spreadsheet_formulas():void
    {
        $user=User::factory()->create();$headers=$this->apiHeaders($user);$category=$user->categories()->create(['name'=>'Categoria','type'=>'DESPESA']);
        $user->transactions()->create(['description'=>'=HYPERLINK("example")','category_id'=>$category->id,'transaction_type'=>'DESPESA','transaction_date'=>'2027-01-10','competence_year'=>2027,'competence_month'=>1,'amount'=>'12.34','payment_method'=>'PIX','status'=>'PAGA']);
        $response=$this->getJson('/api/reports/export?year=2027&month=1',$headers)->assertOk()->assertHeader('Content-Type','text/csv; charset=UTF-8');
        $csv=$response->streamedContent();$this->assertStringContainsString("'=HYPERLINK",$csv);$this->assertStringContainsString('12,34',$csv);
        $this->assertStringNotContainsString('HYPERLINK',$this->getJson('/api/reports/export?year=2028',$headers)->streamedContent());
    }
}
