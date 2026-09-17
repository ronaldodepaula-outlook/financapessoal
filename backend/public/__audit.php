<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

header('Content-Type: application/json');
use Illuminate\Support\Facades\DB;

$result = [];

$result['transactions_by_competence'] = DB::table('transactions')
    ->selectRaw('competence_year, competence_month, transaction_type, status, count(*) as total')
    ->groupBy('competence_year', 'competence_month', 'transaction_type', 'status')
    ->orderBy('competence_year')->orderBy('competence_month')
    ->get();

$result['transactions_flags'] = DB::table('transactions')
    ->selectRaw('count(*) as total, sum(installment_id is not null) as with_installment, sum(loan_id is not null) as with_loan, sum(fixed_expense_id is not null) as with_fixed, sum(subscription_id is not null) as with_subscription, sum(income_schedule_id is not null) as with_income_schedule, sum(card_invoice_id is not null) as with_invoice')
    ->first();

$result['transfers_by_month'] = DB::table('transfers')
    ->selectRaw("DATE_FORMAT(transfer_date,'%Y-%m') as ym, status, count(*) as total")
    ->groupBy('ym', 'status')->orderBy('ym')->get();

$result['card_invoices'] = DB::table('card_invoices')
    ->select('id', 'card_id', 'competence_year', 'competence_month', 'closing_date', 'due_date', 'status')
    ->orderBy('card_id')->orderBy('competence_year')->orderBy('competence_month')
    ->get();

$result['card_invoice_payments_by_month'] = DB::table('card_invoice_payments')
    ->selectRaw("DATE_FORMAT(payment_date,'%Y-%m') as ym, status, count(*) as total")
    ->groupBy('ym', 'status')->orderBy('ym')->get();

$result['loan_payments_by_month'] = DB::table('loan_payments')
    ->selectRaw("DATE_FORMAT(payment_date,'%Y-%m') as ym, payment_type, status, count(*) as total")
    ->groupBy('ym', 'payment_type', 'status')->orderBy('ym')->get();

$result['loan_balance_snapshots_by_month'] = DB::table('loan_balance_snapshots')
    ->selectRaw("DATE_FORMAT(reported_at,'%Y-%m') as ym, count(*) as total")
    ->groupBy('ym')->orderBy('ym')->get();

$result['goal_contributions_by_month'] = DB::table('goal_contributions')
    ->selectRaw("DATE_FORMAT(contribution_date,'%Y-%m') as ym, count(*) as total")
    ->groupBy('ym')->orderBy('ym')->get();

$result['budgets_by_period'] = DB::table('budgets')
    ->selectRaw('year, month, count(*) as total')
    ->groupBy('year', 'month')->orderBy('year')->orderBy('month')
    ->get();

$result['loans'] = DB::table('loans')->select('id', 'name', 'status', 'start_date', 'first_due_date', 'end_date')->get();

$result['fixed_expenses'] = DB::table('fixed_expenses')->select('id', 'description', 'due_day', 'start_date', 'end_date', 'active')->get();
$result['subscriptions'] = DB::table('subscriptions')->select('id', 'name', 'next_due_date', 'billing_anchor_date', 'start_date', 'end_date', 'active')->get();
$result['income_schedules'] = DB::table('income_schedules')->select('id', 'description', 'day', 'start_date', 'end_date', 'active')->get();

$result['imports'] = DB::table('imports')->select('id', 'original_filename', 'status', 'account_id', 'card_id')->get();

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
