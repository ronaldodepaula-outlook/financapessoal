<?php

namespace App\Helpers;

final class Money
{
    public static function add(string $left, string $right): string
    {
        return bcadd($left, $right, 2);
    }

    public static function subtract(string $left, string $right): string
    {
        return bcsub($left, $right, 2);
    }

    public static function normalize(string $amount): string
    {
        return bcadd($amount, '0', 2);
    }

    /** @return list<string> */
    public static function split(string $total, int $count): array
    {
        if ($count < 1 || $count > 600 || ! preg_match('/^\d{1,13}(\.\d{1,2})?$/D', $total)) {
            throw new \InvalidArgumentException('Parcelamento inválido.');
        }
        $cents = (int) bcmul($total, '100', 0);
        if ($cents < $count) {
            throw new \InvalidArgumentException('Cada parcela deve ter ao menos um centavo.');
        }
        $base = intdiv($cents, $count);
        $remainder = $cents % $count;
        $parts = [];
        for ($i = 0; $i < $count; $i++) {
            $parts[] = bcdiv((string) ($base + ($i < $remainder ? 1 : 0)), '100', 2);
        }

        return $parts;
    }
}
