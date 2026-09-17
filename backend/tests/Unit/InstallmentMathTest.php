<?php

namespace Tests\Unit;

use App\Helpers\Money;
use PHPUnit\Framework\TestCase;

class InstallmentMathTest extends TestCase
{
    public function test_remainder_cents_are_distributed_without_changing_total(): void
    {
        $this->assertSame(['33.34', '33.33', '33.33'], Money::split('100.00', 3));
        $this->assertSame(array_fill(0, 12, '100.00'), Money::split('1200.00', 12));
        $sum = '0.00';
        foreach (Money::split('9999999999999.99', 599) as $part) {
            $sum = Money::add($sum, $part);
        }
        $this->assertSame('9999999999999.99', $sum);
    }

    public function test_zero_cent_installments_are_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::split('0.01', 2);
    }
}
