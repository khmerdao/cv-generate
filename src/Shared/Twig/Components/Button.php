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
        $base = 'inline-flex items-center justify-center rounded-full px-6 py-3 text-sm font-semibold transition focus:outline-none focus:ring-2 focus:ring-accent/40';

        return $base.' '.match ($this->variant) {
            'secondary' => 'border-[1.5px] border-ink bg-transparent text-ink hover:bg-ink hover:text-white',
            'accent' => 'bg-pastel-pink text-ink hover:brightness-95',
            'danger' => 'bg-red-600 text-white hover:bg-red-700',
            default => 'bg-ink text-white hover:bg-ink/90',
        };
    }
}
