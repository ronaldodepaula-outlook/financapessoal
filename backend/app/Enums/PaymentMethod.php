<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case PIX = 'PIX';
    case CASH = 'DINHEIRO';
    case DEBIT = 'DEBITO';
    case CREDIT = 'CREDITO';
    case TRANSFER = 'TRANSFERENCIA';
    case BANK_SLIP = 'BOLETO';
    case OTHER = 'OUTROS';
}
