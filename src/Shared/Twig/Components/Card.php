<?php

declare(strict_types=1);

namespace App\Shared\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class Card
{
    public ?string $title = null;
    public string $variant = 'default';

    public function classes(): string
    {
        $base = 'rounded-xl2 p-6';

        return $base.' '.match ($this->variant) {
            'violet' => 'bg-pastel-violet text-ink',
            'yellow' => 'bg-pastel-yellow text-ink',
            'pink' => 'bg-pastel-pink text-ink',
            'mint' => 'bg-pastel-mint text-ink',
            'dark' => 'bg-ink text-white',
            default => 'border border-surface-line bg-surface text-ink shadow-soft',
        };
    }
}
