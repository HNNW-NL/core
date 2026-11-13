<?php

namespace App\Module\Main\Service;

use App\Entity\Account\Profile;
use App\Security\CurrentAccountProvider;

final readonly class CurrentProfileProvider
{
    public function __construct(
        private CurrentAccountProvider $currentAccountProvider,
    ) {
    }

    public function getProfile(): ?Profile
    {
        return $this->currentAccountProvider->getAccount()?->getProfile();
    }
}
