<?php

namespace App\Twig\Components\Shared;

use App\Entity\Account\Profile;
use App\Repository\Account\ProfileRepository;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class ProfileCard
{
    public ?string $id = null;

    public function __construct(
        private readonly ProfileRepository $profileRepository
    ) {
    }

    public function getProfile(): ?Profile
    {
        if (!$this->id) {
            return null;
        }

        return $this->profileRepository->find($this->id);
    }
}
