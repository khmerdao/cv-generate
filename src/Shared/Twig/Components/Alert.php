<?php

declare(strict_types=1);

namespace App\Shared\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class Alert
{
    public string $type = 'info';

    public function classes(): string
    {
        return 'rounded-md px-4 py-3 text-sm '.match ($this->type) {
            'success' => 'bg-green-50 text-green-800',
            'error' => 'bg-red-50 text-red-800',
            default => 'bg-accent-soft text-ink',
        };
    }
}
