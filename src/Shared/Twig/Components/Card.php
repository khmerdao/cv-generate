<?php

declare(strict_types=1);

namespace App\Shared\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class Card
{
    public ?string $title = null;
}
