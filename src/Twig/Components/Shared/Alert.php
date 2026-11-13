<?php

namespace App\Twig\Components\Shared;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class Alert
{
    public ?string $variant = null;
    public function getIcon(): string
    {
        return match ($this->variant) {
            'danger' => 'bi:exclamation-circle',
            'warning' => 'bi:exclamation-triangle',
            'info' => 'bi:info-circle',
            'success' => 'bi:check-circle',
            default => 'bi:circle',
        };
    }
}
