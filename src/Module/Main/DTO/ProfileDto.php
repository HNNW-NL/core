<?php

namespace App\Module\Main\DTO;

class ProfileDto
{
    public string $status;     
    public string $lastLogin;  
    public bool $isOnline;     

    public function __construct(string $status, string $lastLogin, bool $isOnline)
    {
        $this->status = $status;
        $this->lastLogin = $lastLogin;
        $this->isOnline = $isOnline;
    }
}