<?php

namespace App\Enums;

enum AccountType: string
{
    case CHECKING = 'CONTA_CORRENTE';
    case SAVINGS = 'POUPANCA';
    case WALLET = 'CARTEIRA';
    case DIGITAL = 'CONTA_DIGITAL';
    case INVESTMENT = 'INVESTIMENTO';
}
