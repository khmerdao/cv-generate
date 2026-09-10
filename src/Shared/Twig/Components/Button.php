<?php

declare(strict_types=1);

namespace App\Shared\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class Button
{
    public string $variant = 'primary';
    public ?string $href = null;
    public string $type = 'button';

    public function classes(): string
    {
        $base = 'inline-flex items-center justify-center rounded-md px-4 py-2 text-sm font-medium transition focus:outline-none focus:ring-2 focus:ring-accent/40';

        return $base.' '.match ($this->variant) {
            'secondary' => 'border border-surface-line bg-surface text-ink hover:bg-surface-alt',
            'danger' => 'bg-red-600 text-white hover:bg-red-700',
            default => 'bg-accent text-white hover:bg-accent-hover',
        };
    }
}
