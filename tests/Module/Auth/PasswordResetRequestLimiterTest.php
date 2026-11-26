<?php

namespace App\Tests\Module\Auth;

use App\Module\Auth\Service\PasswordResetRequestLimiter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

final class PasswordResetRequestLimiterTest extends TestCase
{
    public function testSameIpCannotBypassLimitByChangingEmail(): void
    {
        $limiter = $this->limiter();
        for ($attempt = 1; $attempt <= 5; ++$attempt) {
            self::assertTrue($limiter->consume('192.0.2.1', 'user'.$attempt.'@example.test')->isAccepted());
        }

        $rejected = $limiter->consume('192.0.2.1', 'another@example.test');
        self::assertFalse($rejected->isAccepted());
        self::assertGreaterThan(time(), $rejected->getRetryAfter()->getTimestamp());
        self::assertLessThanOrEqual(time() + 901, $rejected->getRetryAfter()->getTimestamp());
    }

    public function testSameEmailCannotBypassLimitByChangingIpOrLetterCase(): void
    {
        $limiter = $this->limiter();
        for ($attempt = 1; $attempt <= 5; ++$attempt) {
            self::assertTrue($limiter->consume('192.0.2.'.$attempt, 'user@example.test')->isAccepted());
        }

        self::assertFalse($limiter->consume('192.0.2.6', '  USER@EXAMPLE.TEST  ')->isAccepted());
        self::assertTrue($limiter->consume('192.0.2.7', 'different@example.test')->isAccepted());
    }

    public function testRejectedIpDoesNotUseAnotherEmailsAllowance(): void
    {
        $limiter = $this->limiter();
        for ($attempt = 1; $attempt <= 5; ++$attempt) {
            $limiter->consume('192.0.2.1', 'first@example.test');
        }
        self::assertFalse($limiter->consume('192.0.2.1', 'second@example.test')->isAccepted());

        for ($attempt = 1; $attempt <= 5; ++$attempt) {
            self::assertTrue($limiter->consume('192.0.2.2', 'second@example.test')->isAccepted());
        }
        self::assertFalse($limiter->consume('192.0.2.2', 'second@example.test')->isAccepted());
    }

    private function limiter(): PasswordResetRequestLimiter
    {
        $storage = new InMemoryStorage();
        $configuration = ['policy' => 'sliding_window', 'limit' => 5, 'interval' => '15 minutes'];

        return new PasswordResetRequestLimiter(
            new RateLimiterFactory(['id' => 'test-ip'] + $configuration, $storage),
            new RateLimiterFactory(['id' => 'test-email'] + $configuration, $storage),
            'test-secret',
        );
    }
}
