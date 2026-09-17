<?php

namespace App\Console\Commands;

use App\Helpers\FinancialCalendar;
use App\Models\User;
use App\Services\RecurrenceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class GenerateForecasts extends Command
{
    protected $signature = 'finance:generate-forecasts {--from= : Competência YYYY-MM; padrão mês atual} {--months=2} {--user=}';

    protected $description = 'Gera previsões de recorrências ativas sem duplicar competências.';

    public function handle(RecurrenceService $service): int
    {
        $data = ['from' => $this->option('from') ?: now()->format('Y-m'), 'months' => $this->option('months'), 'user' => $this->option('user')];
        $v = Validator::make($data, ['from' => 'required|date_format:Y-m', 'months' => 'required|integer|between:1,24', 'user' => 'nullable|integer|min:1']);
        if ($v->fails()) {
            $this->error('Parâmetros inválidos. Use --from=YYYY-MM --months=1..24.');

            return self::FAILURE;
        }
        [$year,$month] = array_map('intval', explode('-', $data['from']));
        if ($year < 1900 || $year > 2200 || $year * 12 + $month + (int) $data['months'] - 1 > 2200 * 12 + 12) {
            $this->error('Período fora do limite.');

            return self::FAILURE;
        }
        $users = User::where('status', 'ATIVO');
        if ($data['user']) {
            $users->whereKey($data['user']);
        }
        $created = 0;
        $warnings = 0;
        foreach ($users->cursor() as $user) {
            $result = $service->generate($user, FinancialCalendar::month($year, $month), (int) $data['months']);
            $created += $result['created'];
            $warnings += count($result['warnings']);
        }
        $this->info("Previsões criadas: {$created}; configurações que precisam de revisão: {$warnings}.");

        return $warnings ? self::FAILURE : self::SUCCESS;
    }
}
