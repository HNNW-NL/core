<?php

namespace App\Module\Main\Service;

use App\Entity\Account\Account;
use App\Entity\Account\Profile;
use App\Module\Main\DTO\LocationResultDto;
use App\Module\Main\DTO\UpdateLocationDto;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class LocationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function updateLocation(?Account $account, UpdateLocationDto $dto, SessionInterface $session): LocationResultDto
    {
        if (!$dto->isValid()) {
            return new LocationResultDto(
                success: false,
                message: 'Geen locatie ingevuld'
            );
        }

        if ($account === null) {
            return new LocationResultDto(
                success: false,
                message: 'Geen account gevonden'
            );
        }

        return $this->updateForAccount($account, $dto);
    }

    private function updateForAccount(Account $account, UpdateLocationDto $dto): LocationResultDto
    {
        $profile = $account->getProfile();

        if (!$profile) {
            $profile = new Profile();
            $profile->setAccount($account);
            $profile->setFirstName('User');
            $profile->setLastName('User');
            $profile->setAvatarUrl('default.png');
            $this->entityManager->persist($profile);
        }

        $profile->setLocation($dto->location);
        $this->entityManager->flush();

        return new LocationResultDto(
            success: true,
            location: $profile->getLocation()
        );
    }
}