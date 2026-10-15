<?php

namespace App\Module\Admin\Service\Paginate\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
/**
 * Add a join to the given table
 */
class Join{
    public function __construct(public readonly string $table,
        public readonly string $column)
    {
    }
}