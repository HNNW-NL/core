<?php

namespace App\Module\Main\Service;

use App\Entity\Account\Profile;
use Symfony\Bundle\SecurityBundle\Security;
final readonly class CurrentProfileProvider
{
    public function __construct(
        private Security $security,
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