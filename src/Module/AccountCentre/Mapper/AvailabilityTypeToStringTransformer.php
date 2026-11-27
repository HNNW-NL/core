<?php

namespace App\Module\AccountCentre\Mapper;

use App\Module\AccountCentre\Enum\AvailabilityType;
use Symfony\Component\ObjectMapper\TransformCallableInterface;

class AvailabilityTypeToStringTransformer implements TransformCallableInterface
{
    public function __invoke(mixed $value, object $source, ?object $target): mixed
    {
        if (!$value instanceof AvailabilityType) {
            return $value;
        }

        return $value->value;
    }
}