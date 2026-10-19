<?php

namespace App\Module\Admin\Service\Paginate\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
/**
 * Add a join to the given table
 */
class Join{
    /**
    *   @param string $table name of table to join
    *   @param string $column name of column to use for join
    */
    public function __construct(public readonly string $table,
        public readonly string $column)
    {
    }
}