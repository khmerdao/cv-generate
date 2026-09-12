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
        return 'rounded-2xl px-4 py-3 text-sm '.match ($this->type) {
            'success' => 'bg-pastel-mint text-ink',
            'error' => 'bg-red-50 text-red-800',
            default => 'bg-pastel-violet text-ink',
        };
    }
}
