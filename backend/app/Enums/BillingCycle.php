<?php

namespace App\Enums;

enum BillingCycle: string
{
    case MONTHLY = 'MENSAL';
    case QUARTERLY = 'TRIMESTRAL';
    case SEMIANNUAL = 'SEMESTRAL';
    case ANNUAL = 'ANUAL';
}
