<?php

namespace App\Enums;

enum LoanPaymentType: string
{
    case INSTALLMENT = 'PARCELA';
    case AMORTIZATION = 'AMORTIZACAO';
    case PAYOFF = 'QUITACAO';
}
