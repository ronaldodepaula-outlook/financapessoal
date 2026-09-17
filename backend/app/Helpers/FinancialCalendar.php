<?php

namespace App\Helpers;

use Carbon\CarbonImmutable;

final class FinancialCalendar
{
    public static function month(int $year, int $month): CarbonImmutable
    {
        if ($year < 1900 || $year > 2200 || $month < 1 || $month > 12) {
            throw new \InvalidArgumentException('Competência inválida.');
        }

        return CarbonImmutable::create($year, $month, 1, 0, 0, 0);
    }

    public static function due(CarbonImmutable $month, int $day): CarbonImmutable
    {
        return $month->startOfMonth()->setDay(min($day, $month->daysInMonth));
    }

    public static function cycleMonths(string $cycle): int
    {
        return match ($cycle) {
            'MENSAL' => 1, 'TRIMESTRAL' => 3, 'SEMESTRAL' => 6, 'ANUAL' => 12
        };
    }
}
