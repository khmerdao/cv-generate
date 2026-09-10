<?php

declare(strict_types=1);

namespace App\Tailoring\Enum;

enum TailoringStatus: string
{
    case Pending = 'pending';
    case Fetching = 'fetching';
    case NeedsManualInput = 'needs_manual_input';
    case Analyzing = 'analyzing';
    case Generating = 'generating';
    case Done = 'done';
    case Failed = 'failed';

    public function isTerminal(): bool
    {
        return self::Done === $this || self::Failed === $this;
    }
}
