<?php

namespace App\Twig\Components\Shared\Search;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
/**
 * Add a link to all objects in the given column
 */
class Path
{
     /**
    *   @param string $path path of link
    */
    public function __construct( public readonly string $path)
    {

    }
}