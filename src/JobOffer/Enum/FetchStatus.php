<?php

declare(strict_types=1);

namespace App\JobOffer\Enum;

enum FetchStatus: string
{
    case Pending = 'pending';
    case Fetched = 'fetched';
    case Manual = 'manual';
    case Failed = 'failed';
}
