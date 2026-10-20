<?php

namespace App\Twig\Components\Shared\Search;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS|Attribute::IS_REPEATABLE)]
/**
 * Add a parameter to save to be pased in used links
 */
class PathParameter
{
    /**
    *   @param string $name name of the variable that is used as parameter
    *   @param string $method name of the method to get the parameter with if null uses get + $name with the first letter capitalized e.g. $name = 'id' $method = 'getId'
    */
    public function __construct( public readonly string $name,public readonly string $method)
    {

    }
}