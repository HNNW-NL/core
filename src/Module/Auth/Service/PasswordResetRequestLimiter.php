<?php

namespace App\Module\Auth\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

final class PasswordResetRequestLimiter
{
    public function __construct(
        #[Autowire(service: 'limiter.password_reset_ip')]
        private readonly RateLimiterFactoryInterface $ipLimiter,
        #[Autowire(service: 'limiter.password_reset_email')]
        private readonly RateLimiterFactoryInterface $emailLimiter,
        #[Autowire('%kernel.secret%')]
        private readonly string $secret,
    ) {
    }

    public function consume(?string $clientIp, string $email): RateLimit
    {
        $ipKey = hash_hmac('sha256', $clientIp ?? 'unknown', $this->secret);
        $ipLimit = $this->ipLimiter->create($ipKey)->consume();
        if (!$ipLimit->isAccepted()) {
            return $ipLimit;
        }

        $emailKey = hash_hmac('sha256', strtolower(trim($email)), $this->secret);

        return $this->emailLimiter->create($emailKey)->consume();
    }
}
