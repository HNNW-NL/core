<?php

namespace App\Module\Main\Service;

use App\Module\Main\Mapper\ProfileMapper;
use App\Module\Main\DTO\ProfileDto;

class ProfileService
{
    private ProfileMapper $mapper;

    public function __construct(ProfileMapper $mapper)
    {
        $this->mapper = $mapper;
    }

    public function getProfileData($user): ProfileDto
    {
        // De service vraagt de mapper om de user om te zetten naar het DTO-pakketje
        return $this->mapper->mapToDto($user);
    }
}