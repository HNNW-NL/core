<?php

namespace App\Module\Main\DTO;

class LocationResultDto
{
    public readonly bool $success;
    public readonly ?string $location;
    public readonly bool $isGuest;
    public readonly ?string $message;

    public function __construct(
        bool $success,
        ?string $location = null,
        bool $isGuest = false,
        ?string $message = null
    ) {
        $this->success = $success;
        $this->location = $location;
        $this->isGuest = $isGuest;
        $this->message = $message;
    }

    public function toArray(): array
    {
        $result = ['success' => $this->success];

        if ($this->location !== null) {
            $result['location'] = $this->location;
        }

        if ($this->isGuest) {
            $result['guest'] = true;
        }

        if ($this->message !== null) {
            $result['message'] = $this->message;
        }

        return $result;
    }
}