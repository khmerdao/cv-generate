<?php

declare(strict_types=1);

namespace App\Billing\Enum;

enum Plan: string
{
    case Free = 'free';
    case Pro = 'pro';
}
