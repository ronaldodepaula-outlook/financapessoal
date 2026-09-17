<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\GoalController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\InstallmentController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\PlanningController;
use App\Http\Controllers\RecurrenceController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ImportController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class)->middleware('throttle:api');

Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/auth/refresh', [AuthController::class, 'refresh'])->middleware('throttle:api');
Route::middleware(['jwt', 'throttle:api'])->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::get('/dashboard', [ReportController::class, 'dashboard']);
    Route::get('/reports/summary', [ReportController::class, 'summary']);
    Route::get('/reports/transactions', [ReportController::class, 'transactions']);
    Route::get('/reports/export', [ReportController::class, 'export']);
    Route::get('/imports', [ImportController::class, 'index']);
    Route::post('/imports', [ImportController::class, 'store']);
    Route::get('/imports/{id}', [ImportController::class, 'show'])->whereNumber('id');
    Route::get('/imports/{id}/rows', [ImportController::class, 'rows'])->whereNumber('id');
    Route::put('/imports/{id}/rows', [ImportController::class, 'bulkUpdateRows'])->whereNumber('id');
    Route::put('/imports/{id}/mapping', [ImportController::class, 'mapping'])->whereNumber('id');
    Route::post('/imports/{id}/confirm', [ImportController::class, 'confirm'])->whereNumber('id');
    Route::put('/import-rows/{id}', [ImportController::class, 'editRow'])->whereNumber('id');
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/forecasts/generate', [RecurrenceController::class, 'generate']);
    Route::post('/planning/initialize', [PlanningController::class, 'initialize']);
    Route::get('/preferences', [PlanningController::class, 'preferences']);
    Route::put('/preferences', [PlanningController::class, 'updatePreferences']);
    Route::get('/budgets', [BudgetController::class, 'index']);
    Route::post('/budgets', [BudgetController::class, 'store']);
    Route::post('/budgets/copy', [BudgetController::class, 'copy']);
    Route::get('/budgets/comparison', [BudgetController::class, 'comparison']);
    Route::get('/budgets/fortnight', [BudgetController::class, 'fortnight']);
    Route::get('/budgets/{id}', [BudgetController::class, 'show'])->whereNumber('id');
    Route::put('/budgets/{id}', [BudgetController::class, 'update'])->whereNumber('id');
    Route::get('/goals', [GoalController::class, 'index']);
    Route::post('/goals', [GoalController::class, 'store']);
    Route::get('/goals/{id}', [GoalController::class, 'show'])->whereNumber('id');
    Route::put('/goals/{id}', [GoalController::class, 'update'])->whereNumber('id');
    Route::delete('/goals/{id}', [GoalController::class, 'destroy'])->whereNumber('id');
    Route::get('/goals/{id}/contributions', [GoalController::class, 'contributions'])->whereNumber('id');
    Route::post('/goals/{id}/contributions', [GoalController::class, 'contribute'])->whereNumber('id');
    Route::delete('/goal-contributions/{id}', [GoalController::class, 'cancelContribution'])->whereNumber('id');
    Route::get('/loans', [LoanController::class, 'index']);
    Route::post('/loans', [LoanController::class, 'store']);
    Route::get('/loans/{id}', [LoanController::class, 'show'])->whereNumber('id');
    Route::put('/loans/{id}', [LoanController::class, 'update'])->whereNumber('id');
    Route::delete('/loans/{id}', [LoanController::class, 'destroy'])->whereNumber('id');
    Route::post('/loans/{id}/activate', [LoanController::class, 'activate'])->whereNumber('id');
    Route::get('/loans/{id}/payments', [LoanController::class, 'payments'])->whereNumber('id');
    Route::post('/loans/{id}/payments', [LoanController::class, 'pay'])->whereNumber('id');
    Route::get('/loans/{id}/balances', [LoanController::class, 'balances'])->whereNumber('id');
    Route::post('/loans/{id}/balances', [LoanController::class, 'reportBalance'])->whereNumber('id');
    Route::delete('/loan-payments/{id}', [LoanController::class, 'cancelPayment'])->whereNumber('id');
    foreach (['fixed-expenses', 'subscriptions', 'income-schedules'] as $recurrence) {
        Route::get('/'.$recurrence, [RecurrenceController::class, 'index'])->defaults('recurrence', $recurrence);
        Route::post('/'.$recurrence, [RecurrenceController::class, 'store'])->defaults('recurrence', $recurrence);
        Route::get('/'.$recurrence.'/{id}', [RecurrenceController::class, 'show'])->whereNumber('id')->defaults('recurrence', $recurrence);
        Route::put('/'.$recurrence.'/{id}', [RecurrenceController::class, 'update'])->whereNumber('id')->defaults('recurrence', $recurrence);
        Route::delete('/'.$recurrence.'/{id}', [RecurrenceController::class, 'destroy'])->whereNumber('id')->defaults('recurrence', $recurrence);
    }
    Route::get('/installments', [InstallmentController::class, 'index']);
    Route::post('/installments', [InstallmentController::class, 'store']);
    Route::get('/installments/{id}', [InstallmentController::class, 'show'])->whereNumber('id');
    Route::delete('/installments/{id}', [InstallmentController::class, 'destroy'])->whereNumber('id');
    foreach (['transactions' => TransactionController::class, 'transfers' => TransferController::class] as $resource => $controller) {
        Route::get('/'.$resource, [$controller, 'index']);
        Route::post('/'.$resource, [$controller, 'store']);
        Route::get('/'.$resource.'/{id}', [$controller, 'show'])->whereNumber('id');
        Route::put('/'.$resource.'/{id}', [$controller, 'update'])->whereNumber('id');
        Route::delete('/'.$resource.'/{id}', [$controller, 'destroy'])->whereNumber('id');
    }
    Route::get('/card-invoices', [InvoiceController::class, 'index']);
    Route::post('/card-invoices', [InvoiceController::class, 'store']);
    Route::get('/card-invoices/{id}', [InvoiceController::class, 'show'])->whereNumber('id');
    Route::get('/card-invoices/{id}/payments', [InvoiceController::class, 'payments'])->whereNumber('id');
    Route::post('/card-invoices/{id}/payments', [InvoiceController::class, 'pay'])->whereNumber('id');
    Route::post('/card-invoices/{id}/link-transactions', [InvoiceController::class, 'linkTransactions'])->whereNumber('id');
    Route::delete('/card-invoice-payments/{id}', [InvoiceController::class, 'cancelPayment'])->whereNumber('id');
    foreach (['accounts', 'cards', 'categories', 'merchants'] as $catalog) {
        Route::get('/'.$catalog, [CatalogController::class, 'index'])->defaults('catalog', $catalog);
        Route::post('/'.$catalog, [CatalogController::class, 'store'])->defaults('catalog', $catalog);
        Route::get('/'.$catalog.'/{id}', [CatalogController::class, 'show'])->whereNumber('id')->defaults('catalog', $catalog);
        Route::put('/'.$catalog.'/{id}', [CatalogController::class, 'update'])->whereNumber('id')->defaults('catalog', $catalog);
        Route::delete('/'.$catalog.'/{id}', [CatalogController::class, 'destroy'])->whereNumber('id')->defaults('catalog',$catalog);
    }
});
