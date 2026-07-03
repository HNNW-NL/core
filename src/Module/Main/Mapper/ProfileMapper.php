<?php

namespace App\Module\Main\Mapper;

use App\Module\Main\DTO\ProfileDto;

class ProfileMapper
{
    public function mapToDto($user): ProfileDto
    {
        // 1. Vertaal de status (Dit stond eerst in jouw Twig code!)
        $statusText = 'Offline';
        $isOnline = false;

        if ($user->getStatus() !== null) {
            if ($user->getStatus()->getId() === 1) {
                $statusText = 'Online';
                $isOnline = true;
            } elseif ($user->getStatus()->getId() === 2) {
                $statusText = 'Afwezig';
            }
        }

        // 2. Vertaal de datum naar nette tekst
        $lastLoginText = 'Onbekend';
        if ($user->getLastLoginAt() !== null) {
            $lastLoginText = $user->getLastLoginAt()->format('d-m-Y H:i');
        }

        // 3. Stop alles in het DTO-pakketje
        return new ProfileDto($statusText, $lastLoginText, $isOnline);
    }
}