<?php

namespace App\Security;

use App\Entity\Account\Account;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class CurrentAccountProvider
{
    public function __construct(private Security $security)
    {
    }

    public function getAccount(): ?Account
    {
        $user = $this->security->getUser();
        return $user instanceof Account ? $user : null;
    }
}
