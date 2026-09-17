<?php

namespace App\Enums;

enum TransactionType: string
{
    case INCOME = 'RECEITA';
    case EXPENSE = 'DESPESA';
}
