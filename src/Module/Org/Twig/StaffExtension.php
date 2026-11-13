<?php

namespace App\Module\Org\Twig;

use App\Module\Org\Service\StaffViewService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class StaffExtension extends AbstractExtension
{
    public function __construct(
        private StaffViewService $service,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('org_staff', [$this, 'getStaff']),
        ];
    }

    public function getStaff(): array
    {
        return $this->service->getStaff();
    }
}