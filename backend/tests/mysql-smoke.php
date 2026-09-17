<?php

// Verificação opt-in no banco local: somente DML dentro de transação revertida.
// Não executa migrate:fresh, DROP, TRUNCATE ou COMMIT.
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! $app->environment('local') || config('database.default') !== 'mysql') {
    fwrite(STDERR, "Execute apenas no ambiente local com driver mysql.\n");
    exit(1);
}

$check = function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};
$reject = function (Closure $operation) use ($check): void {
    try {
        $operation();
    } catch (QueryException $exception) {
        $check($exception->getCode() === '23000', 'SQLSTATE de integridade esperado.');

        return;
    }
    throw new RuntimeException('O banco deveria rejeitar o registro.');
};

DB::beginTransaction();
try {
    $first = User::factory()->create();
    $second = User::factory()->create();
    $category = $first->categories()->create(['name' => 'Verificação de integridade', 'type' => 'DESPESA']);
    $reject(fn () => $first->categories()->create(['name' => 'Verificação de integridade', 'type' => 'DESPESA']));
    $account = $second->accounts()->create(['name' => 'Verificação', 'account_type' => 'CARTEIRA', 'initial_balance' => '0.10']);
    $check($account->fresh()->initial_balance === '0.10', 'DECIMAL deve preservar centavos.');
    $budget = ['year' => 2027, 'month' => 1, 'category_id' => $category->id, 'planned_amount' => '2400.00'];
    $first->budgets()->create($budget);
    $reject(fn () => $first->budgets()->create($budget));
    $reject(fn () => $first->transactions()->create([
        'account_id' => $account->id, 'category_id' => $category->id,
        'description' => 'Teste entre usuários', 'transaction_type' => 'DESPESA',
        'transaction_date' => '2027-01-01', 'competence_month' => 1, 'competence_year' => 2027,
        'amount' => '1024.77', 'payment_method' => 'PIX',
    ]));
    fwrite(STDOUT, "PASS: DECIMAL, unicidade de categorias/orçamentos com NULL e FK entre usuários no MySQL/MariaDB.\n");
} finally {
    DB::rollBack();
    fwrite(STDOUT, "Transação revertida; registros de teste não foram persistidos.\n");
}
