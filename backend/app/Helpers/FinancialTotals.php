<?php

namespace App\Helpers;

final class FinancialTotals
{
    public static function sum(iterable $rows, string $field = 'amount'): string
    {
        $total = '0.00';
        foreach ($rows as $row) {
            $total = Money::add($total, (string) data_get($row, $field));
        }

return $total;
    }

    public static function percentage(string $part, string $total): ?string
    {
        return bccomp($total, '0', 2) === 0 ? null : bcdiv(bcmul($part, '100', 4), $total, 2);
    }
}
