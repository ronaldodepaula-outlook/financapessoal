<?php

namespace App\Enums;

enum LoanStatus: string
{
    case DRAFT = 'RASCUNHO';
    case ACTIVE = 'ATIVO';
    case SETTLED = 'QUITADO';
    case CANCELLED = 'CANCELADO';
}
