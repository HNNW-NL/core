<?php

namespace App\Module\Main\DTO;

final readonly class ChangeWorkPackageStatusDTO
{
    public function __construct(
        public string $workPackageId,
        public string $statusName,
    ) {
    }
}