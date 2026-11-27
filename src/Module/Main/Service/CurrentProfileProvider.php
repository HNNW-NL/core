<?php

namespace App\Module\Main\Service;

use App\Entity\Account\Profile;
use Symfony\Bundle\SecurityBundle\Security;
class CurrentProfileProvider
{
    public function __construct(
        private readonly Security $security,
    ) {
    }

    public function getProfile(): ?Profile
    {
        $user = $this->security->getUser();

        if ($user === null || !method_exists($user, 'getProfile')) {
            return null;
        }
        $profile = $user->getProfile();

        return $profile instanceof Profile ? $profile : null;
    }
}