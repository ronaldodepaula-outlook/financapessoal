<?php

namespace App\Services;

use App\Helpers\FinancialTotals;
use App\Helpers\Money;
use App\Models\User;
use App\Repositories\ReportRepository;

class ReportService
{
    public function __construct(private ReportRepository $repository,private BudgetService $budgets,private BalanceService $balances){}
    public function summarize(iterable $rows):array
    {
        $totals=array_fill_keys(['income','expenses','cash_expenses','expected_income','committed_expenses','committed_cash_expenses','fixed_expenses','variable_expenses','installments','payroll_loan','payroll_already_deducted'],'0.00');
        foreach($rows as$row){
            $paid=$row->status->value==='PAGA';$income=$row->transaction_type->value==='RECEITA';
            $key=$income?'expected_income':'committed_expenses';$totals[$key]=Money::add($totals[$key],$row->amount);
            if(!$income&&$row->cash_flow_effect)$totals['committed_cash_expenses']=Money::add($totals['committed_cash_expenses'],$row->amount);
            if(!$paid)continue;
            $key=$income?'income':'expenses';$totals[$key]=Money::add($totals[$key],$row->amount);
            if($income)continue;
            if($row->cash_flow_effect)$totals['cash_expenses']=Money::add($totals['cash_expenses'],$row->amount);
            else $totals['payroll_already_deducted']=Money::add($totals['payroll_already_deducted'],$row->amount);
            $key=$row->is_fixed?'fixed_expenses':'variable_expenses';$totals[$key]=Money::add($totals[$key],$row->amount);
            if($row->installment_id||$row->loan_id)$totals['installments']=Money::add($totals['installments'],$row->amount);
            if($row->loan?->loan_type->value==='CONSIGNADO')$totals['payroll_loan']=Money::add($totals['payroll_loan'],$row->amount);
        }
        return [...$totals,'balance'=>Money::subtract($totals['income'],$totals['cash_expenses']),'projected_balance'=>Money::subtract($totals['expected_income'],$totals['committed_cash_expenses'])];
    }
    public function grouped(iterable $rows,string $group):array
    {
        $groups=[];
        foreach($rows as$row){
            [$key,$label]=match($group){
                'month'=>[sprintf('%04d-%02d',$row->competence_year,$row->competence_month),sprintf('%02d/%04d',$row->competence_month,$row->competence_year)],
                'year'=>[(string)$row->competence_year,(string)$row->competence_year],
                'fixed'=>[$row->is_fixed?'fixed':'variable',$row->is_fixed?'Fixas':'Variáveis'],
                default=>[(string)($row->{$group.'_id'}??'none'),$row->{$group}?->name??'Sem vínculo'],
            };
            $groups[$key]??=['key'=>$key,'label'=>$label,'income'=>'0.00','expenses'=>'0.00','pending_income'=>'0.00','pending_expenses'=>'0.00','count'=>0];
            $field=($row->status->value==='PENDENTE'?'pending_':'').($row->transaction_type->value==='RECEITA'?'income':'expenses');
            $groups[$key][$field]=Money::add($groups[$key][$field],$row->amount);$groups[$key]['count']++;
        }
        if(in_array($group,['month','year'],true))ksort($groups);
        else uasort($groups,fn($a,$b)=>bccomp($b['expenses'],$a['expenses'],2));
        return array_values($groups);
    }
    public function categoryDetail(iterable $rows):array
    {
        $categories=[];
        foreach($rows as$row){
            $catKey=(string)$row->category_id;
            $categories[$catKey]??=['key'=>$catKey,'label'=>$row->category?->name??'Sem vínculo','income'=>'0.00','expenses'=>'0.00','pending_income'=>'0.00','pending_expenses'=>'0.00','count'=>0,'subcategories'=>[]];
            $field=($row->status->value==='PENDENTE'?'pending_':'').($row->transaction_type->value==='RECEITA'?'income':'expenses');
            $categories[$catKey][$field]=Money::add($categories[$catKey][$field],$row->amount);$categories[$catKey]['count']++;
            $subKey=(string)($row->subcategory_id??'none');
            $categories[$catKey]['subcategories'][$subKey]??=['key'=>$subKey,'label'=>$row->subcategory?->name??'Sem subcategoria','income'=>'0.00','expenses'=>'0.00','pending_income'=>'0.00','pending_expenses'=>'0.00','count'=>0];
            $categories[$catKey]['subcategories'][$subKey][$field]=Money::add($categories[$catKey]['subcategories'][$subKey][$field],$row->amount);$categories[$catKey]['subcategories'][$subKey]['count']++;
        }
        foreach($categories as&$category){
            uasort($category['subcategories'],fn($a,$b)=>bccomp($b['expenses'],$a['expenses'],2));
            $category['subcategories']=array_values($category['subcategories']);
        }
        unset($category);
        uasort($categories,fn($a,$b)=>bccomp($b['expenses'],$a['expenses'],2));
        return array_values($categories);
    }
    public function report(User $user,array $filters):array
    {
        $rows=$this->repository->query($user,$filters)->with(['category','subcategory','account','card','merchant','loan'])->get();
        return ['filters'=>$filters,'totals'=>$this->summarize($rows),'groups'=>$this->grouped($rows,$filters['group_by']??'category'),'records'=>$rows->count()];
    }
    public function dashboard(User $user,int $year,int $month):array
    {
        $rows=$this->repository->query($user,['year'=>$year])->with(['category','subcategory','account','card','loan'])->get();
        $current=$rows->where('competence_month',$month);$budget=$this->budgets->comparison($user,$year,$month);
        $monthly=[];
        for($m=1;$m<=12;$m++)$monthly[]=['month'=>$m,'label'=>sprintf('%02d/%d',$m,$year),...$this->summarize($rows->where('competence_month',$m))];
        $annual=[];
        foreach([$year-1,$year,$year+1]as$y){
            if($y<1900||$y>2200)continue;
            $annual[]=['year'=>$y,...$this->summarize($y===$year?$rows:$this->repository->query($user,['year'=>$y])->with('loan')->get())];
        }
        $accounts=$user->accounts()->where('status','ATIVO')->get()->map(fn($a)=>['id'=>$a->id,'name'=>$a->name,'current_balance'=>$this->balances->account($a)]);
        $future=$user->transactions()->where('status','PENDENTE')->where('transaction_type','DESPESA')->where(fn($q)=>$q->whereNotNull('installment_id')->orWhereNotNull('loan_id'))->where('due_date','>=',sprintf('%04d-%02d-01',$year,$month))->orderBy('due_date')->get();
        $warnings=[];
        if($user->incomeSchedules()->where('active',false)->exists())$warnings[]='Configure e ative suas receitas recorrentes para completar a projeção.';
        if($user->loans()->where('status','RASCUNHO')->exists())$warnings[]='Há contrato em rascunho aguardando confirmação das condições e vencimentos.';
        $breakdown=[];
        foreach(config('initial-planning.monthly_budget_targets') as$target){
            $matching=$current->filter(fn($r)=>$r->transaction_type->value==='DESPESA'&&$r->status->value==='PAGA'&&$r->category?->name===$target['category']&&(!$target['subcategory']||$r->subcategory?->name===$target['subcategory']));
            $breakdown[]=['label'=>$target['subcategory']??$target['category'],'amount'=>FinancialTotals::sum($matching)];
        }
        return ['year'=>$year,'month'=>$month,'summary'=>[...$this->summarize($current),'planned_amount'=>$budget['planned_amount'],'actual_amount'=>$budget['actual_amount'],'budget_usage_percent'=>FinancialTotals::percentage($budget['actual_amount'],$budget['planned_amount'])],
            'by_category'=>$this->categoryDetail($current),'by_account'=>$this->grouped($current,'account'),'by_card'=>$this->grouped($current,'card'),'highlights'=>$breakdown,
            'monthly_evolution'=>$monthly,'annual_evolution'=>$annual,'budget'=>$budget,'fortnight'=>$this->budgets->fortnight($user,$year,$month),
            'accounts'=>$accounts,'future_installments'=>$this->grouped($future,'month'),'upcoming'=>$current->where('status',\App\Enums\PaymentStatus::PENDING)->sortBy('due_date')->take(6)->values(),'warnings'=>$warnings];
    }
}
