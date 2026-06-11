<?php

namespace App\Module\Admin\Mapper;

use App\Entity\Common\Status;
use App\Module\Admin\DTO\StatusDTO;

final class StatusMapper
{
    public function dtoToEntity(StatusDTO $dto): Status
    {
        $status = new Status();

        $status->setName($dto->name);
        $status->setColourHex($dto->colourHex);
        $status->setScope($dto->scope);

        return $status;
    }
}