<?php

namespace App\Module\Main\Exception;

use App\Module\Main\DTO\ProjectApplyResult;
use DomainException;

final class ProjectApplicationBlocked extends DomainException
{
    public function __construct(public readonly ProjectApplyResult $result)
    {
        parent::__construct($result->message);
    }
}
