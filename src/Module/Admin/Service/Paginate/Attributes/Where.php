<?php

namespace App\Module\Admin\Service\Paginate\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
/**
 * Override the where used to search for the given property
 */
class Where{

    /**
    *   @param string $column name of column to search in
    *   @param string $compare compare operater to use
    */
    public function __construct(
        public readonly string $column,
        public readonly string $compare)
    {
    }
}