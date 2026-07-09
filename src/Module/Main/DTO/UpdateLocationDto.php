<?php

namespace App\Module\Main\DTO;

class UpdateLocationDto
{
    public readonly string $location;

    public function __construct(string $location)
    {
        $this->location = $location;
    }

    public static function fromArray(array $data): self
    {
        $location = trim((string) ($data['location'] ?? ''));

        return new self($location);
    }

    public function isValid(): bool
    {
        return $this->location !== '';
    }
}