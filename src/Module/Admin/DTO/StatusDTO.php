<?php

namespace App\Module\Admin\DTO;

final readonly class StatusDTO
{
    public function __construct(
        public string $name,
        public string $colourHex,
        public string $scope,
    ) {
    }
}