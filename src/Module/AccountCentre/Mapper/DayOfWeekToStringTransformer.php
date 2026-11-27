<?php

namespace App\Module\AccountCentre\Mapper;

use App\Module\AccountCentre\Enum\DayOfWeek;
use Symfony\Component\ObjectMapper\TransformCallableInterface;

class DayOfWeekToStringTransformer implements TransformCallableInterface
{
    public function __invoke(mixed $value, object $source, ?object $target): mixed
    {
        if (!$value instanceof DayOfWeek) {
            return $value;
        }

        return $value->value;
    }
}