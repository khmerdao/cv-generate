<?php

declare(strict_types=1);

namespace App\Shared\Twig\Components;

use Symfony\Component\Form\FormView;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class Input
{
    public FormView $field;
}
