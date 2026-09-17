<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

header('Content-Type: application/json');
use Illuminate\Support\Facades\DB;

$mode = $_GET['mode'] ?? 'dry-run';
$backupDir = __DIR__.'/../storage/app/backups/pre-month-shift-20260916-132706';

function shiftDate(?string $value, int $months): ?string {
    if ($value === null) return null;
    $dt = new DateTimeImmutable($value);
    return $dt->modify(($months < 0 ? $months : '+'.$months).' month')->format(strlen($value) > 10 ? 'Y-m-d H:i:s' : 'Y-m-d');
}
function shiftCompetence(int $year, int $month, int $delta): array {
    $total = ($year * 12 + ($month - 1)) + $delta;
    return [intdiv($total, 12), ($total % 12) + 1];
}

$transactions = json_decode(file_get_contents($backupDir.'/transactions.json'), true);
$invoices = json_decode(file_get_contents($backupDir.'/card_invoices.json'), true);
$payments = json_decode(file_get_contents($backupDir.'/card_invoice_payments.json'), true);

$plan = ['transactions' => [], 'card_invoices' => [], 'card_invoice_payments' => []];

foreach ($transactions as $row) {
    [$cy, $cm] = shiftCompetence((int) $row['competence_year'], (int) $row['competence_month'], -1);
    $plan['transactions'][] = [
        'id' => $row['id'],
        'transaction_date' => shiftDate($row['transaction_date'], -1),
        'due_date' => shiftDate($row['due_date'], -1),
        'paid_at' => shiftDate($row['paid_at'], -1),
        'competence_year' => $cy,
        'competence_month' => $cm,
    ];
}
foreach ($invoices as $row) {
    [$cy, $cm] = shiftCompetence((int) $row['competence_year'], (int) $row['competence_month'], -1);
    $plan['card_invoices'][] = [
        'id' => $row['id'],
        'closing_date' => shiftDate($row['closing_date'], -1),
        'due_date' => shiftDate($row['due_date'], -1),
        'competence_year' => $cy,
        'competence_month' => $cm,
    ];
}
foreach ($payments as $row) {
    $plan['card_invoice_payments'][] = [
        'id' => $row['id'],
        'payment_date' => shiftDate($row['payment_date'], -1),
    ];
}

$result = ['mode' => $mode, 'counts' => ['transactions' => count($plan['transactions']), 'card_invoices' => count($plan['card_invoices']), 'card_invoice_payments' => count($plan['card_invoice_payments'])]];
$result['sample_transactions'] = array_slice($plan['transactions'], 0, 3);
$result['sample_invoices'] = $plan['card_invoices'];

if ($mode !== 'apply') {
    $result['note'] = 'Dry-run: nenhuma alteração feita. Use ?mode=apply para restaurar ao deslocamento correto de 1 mês (idempotente: pode rodar mais de uma vez sem efeito colateral).';
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

DB::transaction(function () use ($plan, &$result) {
    foreach ($plan['transactions'] as $row) {
        $id = $row['id']; unset($row['id']);
        DB::table('transactions')->where('id', $id)->update($row);
    }
    foreach ($plan['card_invoices'] as $row) {
        $id = $row['id']; unset($row['id']);
        DB::table('card_invoices')->where('id', $id)->update($row);
    }
    foreach ($plan['card_invoice_payments'] as $row) {
        $id = $row['id']; unset($row['id']);
        DB::table('card_invoice_payments')->where('id', $id)->update($row);
    }
});

$result['verify_transactions_by_competence'] = DB::table('transactions')->selectRaw('competence_year, competence_month, count(*) as total')->groupBy('competence_year', 'competence_month')->orderBy('competence_year')->orderBy('competence_month')->get();
$result['verify_card_invoices'] = DB::table('card_invoices')->select('id', 'competence_year', 'competence_month', 'closing_date', 'due_date')->get();
$result['verify_card_invoice_payments'] = DB::table('card_invoice_payments')->select('id', 'payment_date')->get();

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
