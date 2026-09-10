<?php

declare(strict_types=1);

namespace App\Shared\Message;

final readonly class PingMessage
{
    public function __construct(public string $text)
    {
    }
}
