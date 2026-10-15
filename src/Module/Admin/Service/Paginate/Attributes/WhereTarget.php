<?php

namespace App\Module\Admin\Service\Paginate\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
/**
 * Override the where used to search for the given property
 */
class WhereTarget{

    public function __construct(
        public readonly string $target,
        public readonly string $compare)
    {
    }
}