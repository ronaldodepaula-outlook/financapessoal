<?php

namespace App\Enums;

enum LoanType: string
{
    case PERSONAL = 'PESSOAL';
    case PAYROLL = 'CONSIGNADO';
    case FINANCING = 'FINANCIAMENTO';
    case OTHER = 'OUTROS';
}
