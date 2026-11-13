<?php

namespace App\Module\AccountCentre\DTO;

use App\Module\AccountCentre\Enum\AvailabilityType;
use App\Module\AccountCentre\Enum\DayOfWeek;
use App\Module\AccountCentre\Mapper\AvailabilityTypeToStringTransformer;
use App\Module\AccountCentre\Mapper\DayOfWeekToStringTransformer;
use Symfony\Component\ObjectMapper\Attribute\Map;

class AvailabilityDTO
{
    public function __construct(
        #[Map(transform: AvailabilityTypeToStringTransformer::class)]
        public AvailabilityType $availabilityType,

        public \DateTimeImmutable $validFrom,

        public ?\DateTimeImmutable $validUntil,

        #[Map(transform: DayOfWeekToStringTransformer::class)]
        public DayOfWeek $dayOfWeek,

        public \DateTimeImmutable $startTime,

        public \DateTimeImmutable $endTime,

        public ?string $note = null,
    ) {
    }
}