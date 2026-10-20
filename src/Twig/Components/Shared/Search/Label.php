<?php

namespace App\Twig\Components\Shared\Search;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
/**
 * Override the name used for the result column in search
 */
class Label
{
     /**
    *   @param string $name name that the column should be named
    */
    public function __construct( public readonly string $name)
    {

    }
}