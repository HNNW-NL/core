<?php

namespace App\Module\Auth\Exception;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final class RegistrationValidationException extends \RuntimeException
{
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Registration input is invalid.');
    }
}
