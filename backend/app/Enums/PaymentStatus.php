<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case PENDING = 'PENDENTE';
    case PAID = 'PAGA';
    case CANCELLED = 'CANCELADA';
}
