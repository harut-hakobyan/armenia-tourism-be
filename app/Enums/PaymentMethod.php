<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMethod: string
{
    case PayDriver = 'pay_driver';
    case Deposit = 'deposit';
    case FullOnline = 'full_online';
}
