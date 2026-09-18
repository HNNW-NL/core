<?php

namespace App\Module\AccountCentre\Handler;

use App\Entity\Account\Availability;
use App\Entity\Account\Profile;
use App\Module\AccountCentre\DTO\AvailabilityDTO;
use App\Repository\Account\AvailabilityRepository;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;

class CreateAvailabilityHandler
{
    public function __construct(
        private readonly ObjectMapperInterface $objectMapper,
        private readonly AvailabilityRepository $availabilityRepository,
    ) {
    }

    public function handle(AvailabilityDTO $dto, Profile $profile, bool $flush = true): Availability
    {
        /** @var Availability $availability */
        $availability = $this->objectMapper->map($dto, Availability::class);

        $availability->setProfile($profile);

        $this->availabilityRepository->save($availability, $flush);

        return $availability;
    }
}