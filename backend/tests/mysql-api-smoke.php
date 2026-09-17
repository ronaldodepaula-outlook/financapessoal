<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();
if (! $app->environment('local') || config('database.default') !== 'mysql') {
    fwrite(STDERR, "Execute somente no ambiente local com driver mysql.\n");
    exit(1);
}

$kernel = $app->make(Kernel::class);
$call = function (string $method, string $path, array $data = [], ?string $token = null, int $expected = 200) use ($kernel): array {
    $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'REMOTE_ADDR' => '127.0.0.2'];
    if ($token) {
        $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
    }
    $request = Request::create($path, $method, [], [], [], $server, json_encode($data, JSON_THROW_ON_ERROR));
    $response = $kernel->handle($request);
    if ($response->getStatusCode() !== $expected) {
        throw new RuntimeException($method.' '.$path.' retornou '.$response->getStatusCode().'; esperado '.$expected);
    }

    return json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
};
$check = function (bool $condition, string $message) {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};
DB::beginTransaction();
try {
    $password = bin2hex(random_bytes(20));
    $user = User::create(['name' => 'Verificação temporária', 'email' => 'smoke-'.bin2hex(random_bytes(8)).'@example.test', 'password' => $password]);
    $login = $call('POST', '/api/auth/login', ['email' => $user->email, 'password' => $password])['data'];
    $token = $login['access_token'];
    $call('GET', '/api/auth/me', [], $token);
    $account = $call('POST', '/api/accounts', ['name' => 'Principal', 'account_type' => 'CARTEIRA', 'initial_balance' => '1000.00'], $token, 201)['data'];
    $category = $call('POST', '/api/categories', ['name' => 'Compras', 'type' => 'DESPESA'], $token, 201)['data'];
    $purchase = $call('POST', '/api/installments', ['description' => 'Compra parcelada', 'total_amount' => '100.00', 'total_installments' => 3, 'start_date' => '2028-01-31', 'account_id' => $account['id'], 'category_id' => $category['id'], 'payment_method' => 'BOLETO'], $token, 201)['data'];
    $check(array_column($purchase['schedule'], 'amount') === ['33.34', '33.33', '33.33'], 'Distribuição incorreta de centavos.');
    $call('PUT', '/api/transactions/'.$purchase['schedule'][0]['id'], ['status' => 'PAGA'], $token);
    $balance = $call('GET', '/api/accounts/'.$account['id'], [], $token)['data']['current_balance'];
    $check($balance === '966.66', 'Saldo incorreto após pagamento da parcela.');
    $fixed = $call('POST', '/api/fixed-expenses', ['description' => 'Internet', 'amount' => '99.90', 'due_day' => 31, 'start_date' => '2028-01-01', 'account_id' => $account['id'], 'category_id' => $category['id'], 'payment_method' => 'BOLETO'], $token, 201)['data'];
    $forecast = $call('POST', '/api/forecasts/generate', ['year' => 2028, 'month' => 1, 'months' => 3], $token)['data'];
    $check($forecast['created'] === 2, 'Previsões mensais incorretas.');
    $check($call('POST', '/api/forecasts/generate', ['year' => 2028, 'month' => 1, 'months' => 3], $token)['data']['created'] === 0, 'Previsões duplicadas.');
    $call('POST', '/api/subscriptions', ['name' => 'Anuidade', 'amount' => '50.00', 'start_date' => '2028-01-01', 'next_due_date' => '2028-01-31', 'billing_cycle' => 'ANUAL', 'account_id' => $account['id'], 'category_id' => $category['id'], 'payment_method' => 'PIX'], $token, 201);
    $loan = $call('POST', '/api/loans', ['name' => 'Contrato de teste', 'loan_type' => 'PESSOAL', 'principal_amount' => '180.00', 'installment_amount' => '100.00', 'installments' => 2, 'first_due_date' => '2028-01-31', 'category_id' => $category['id'], 'account_id' => $account['id'], 'cash_flow_mode' => 'CONTA'], $token, 201)['data'];
    $loan = $call('POST', '/api/loans/'.$loan['id'].'/activate', [], $token)['data'];
    $payment = $call('POST', '/api/loans/'.$loan['id'].'/payments', ['payment_type' => 'PARCELA', 'loan_installment_id' => $loan['schedule'][0]['id'], 'amount' => '100.00', 'payment_date' => '2028-01-31'], $token, 201)['data'];
    $check($call('GET', '/api/accounts/'.$account['id'], [], $token)['data']['current_balance'] === '866.66', 'Baixa do empréstimo incorreta.');
    $payoff = $call('POST', '/api/loans/'.$loan['id'].'/payments', ['payment_type' => 'QUITACAO', 'amount' => '90.00', 'payment_date' => '2028-02-01'], $token, 201)['data'];
    $check($call('GET', '/api/loans/'.$loan['id'], [], $token)['data']['remaining_installments'] === 0, 'Quitação não cancelou pendências.');
    $call('DELETE', '/api/loan-payments/'.$payoff['id'], [], $token);
    $call('DELETE', '/api/loan-payments/'.$payment['id'], [], $token);
    $check($call('GET', '/api/accounts/'.$account['id'], [], $token)['data']['current_balance'] === '966.66', 'Estorno incorreto no saldo.');
    $initial = $call('POST', '/api/planning/initialize', [], $token)['data'];
    $check($initial['created']['budgets'] === 168, 'Planejamento inicial incompleto.');
    $renamed = $user->categories()->where('name', 'Alimentação')->whereNull('parent_id')->firstOrFail();
    $call('PUT', '/api/categories/'.$renamed->id, ['name' => 'Alimentação ajustada'], $token);
    $check($call('POST', '/api/planning/initialize', [], $token)['data']['created']['budgets'] === 0, 'Planejamento duplicado.');
    $call('PUT', '/api/preferences', ['income_is_net_of_payroll_loan' => true], $token);
    $payroll = $user->loans()->where('template_key', 'initial-payroll-loan')->firstOrFail();
    $call('POST', '/api/loans/'.$payroll->id.'/activate', ['first_due_date' => '2027-01-10', 'cash_flow_mode' => 'FOLHA'], $token);
    $fortnight = $call('GET', '/api/budgets/fortnight?year=2027&month=1', [], $token)['data'];
    $check($fortnight['periods'][0]['committed_expenses'] === '0.00' && $fortnight['periods'][0]['payroll_already_deducted'] === '1024.77', 'Consignado descontado em duplicidade.');
    $budget = $user->budgets()->where('year', 2027)->firstOrFail();
    $call('PUT', '/api/budgets/'.$budget->id, ['planned_amount' => '123.45', 'reason' => 'Teste de revisão'], $token);
    $check($budget->revisions()->count() === 2, 'Revisão não persistida.');
    $check($call('POST', '/api/budgets/copy', ['source_year' => 2027, 'target_year' => 2028], $token)['data']['skipped'] === 84, 'Cópia sobrescreveu destinos.');
    $call('GET', '/api/budgets/comparison?year=2028&month=1', [], $token);
    $goal = $call('POST', '/api/goals', ['name' => 'Reserva', 'target_amount' => '1000.00'], $token, 201)['data'];
    $contribution = $call('POST', '/api/goals/'.$goal['id'].'/contributions', ['amount' => '123.45', 'contribution_date' => '2028-01-10'], $token, 201)['data'];
    $check($call('GET', '/api/goals/'.$goal['id'], [], $token)['data']['saved_amount'] === '123.45', 'Progresso da meta incorreto.');
    $call('DELETE', '/api/goal-contributions/'.$contribution['id'], [], $token);
    $new = $call('POST', '/api/auth/refresh', ['refresh_token' => $login['refresh_token']])['data'];
    $call('GET', '/api/auth/me', [], $token, 401);
    $call('POST', '/api/auth/logout', [], $new['access_token']);
    $call('GET', '/api/auth/me', [], $new['access_token'], 401);
    fwrite(STDOUT, "PASS: kernel HTTP Laravel com MariaDB — autenticação, cadastros, parcelas, recorrências, empréstimos, quitação/estorno, consignado líquido, planejamento, revisões/cópia e metas.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'FAIL: '.$exception::class." (detalhes e credenciais omitidos).\n");
    $failed = true;
} finally {
    DB::rollBack();
    fwrite(STDOUT, "Transação revertida; nenhum usuário, token ou movimento de teste persistido.\n");
}
exit(isset($failed) ? 1 : 0);
