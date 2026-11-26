<?php

namespace App\Tests\Module\Auth;

use App\Logging\SensitiveAuthDataProcessor;
use App\Security\AuthProfilerMatcher;
use Monolog\{Level, LogRecord};
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\{Request, RequestStack};

final class SensitiveAuthDataProcessorTest extends TestCase
{
    public static function tokenRoutes(): iterable
    {
        yield 'reset' => ['reset-password'];
        yield 'verification' => ['verify-email'];
    }

    #[DataProvider('tokenRoutes')]
    public function testSensitiveUrisAndNestedValuesCannotReachLogs(string $route): void
    {
        $token = str_repeat('a', 64);
        $email = 'private.person@example.test';
        $url = 'https://trusted.example.test/auth/'.$route.'/'.$token.'/'.$email.'?password=private-password';
        $stack = new RequestStack();
        $stack->push(Request::create($url));
        $processor = new SensitiveAuthDataProcessor($stack);
        $record = new LogRecord(new \DateTimeImmutable(), 'request', Level::Error, 'Failed '.$url, [
            'request_uri' => $url, 'route_parameters' => ['token' => $token, 'email' => $email],
            'nested' => ['password' => 'private-password', 'received' => 'serialized-private-data'],
            'exception' => new \RuntimeException('Database failure: '.$url),
        ], ['referer' => $url]);
        $result = json_encode($processor($record), JSON_THROW_ON_ERROR);
        foreach ([$token, $email, 'private-password', 'serialized-private-data', 'Database failure'] as $secret) {
            self::assertStringNotContainsString($secret, $result);
        }
        self::assertStringContainsString('redacted', $result);
    }

    public function testOtherRequestLoggingIsPreserved(): void
    {
        $stack = new RequestStack();
        $stack->push(Request::create('/projects'));
        $record = new LogRecord(new \DateTimeImmutable(), 'request', Level::Info, 'Project loaded.', ['id' => 123]);
        self::assertSame($record, (new SensitiveAuthDataProcessor($stack))($record));
    }

    public function testProfilerCannotCollectAuthenticationPayloads(): void
    {
        $matcher = new AuthProfilerMatcher();
        foreach (['/auth/login', '/auth/register', '/auth/forgot-password', '/auth/reset-password/token/mail'] as $path) {
            self::assertFalse($matcher->matches(Request::create($path)));
        }
        self::assertTrue($matcher->matches(Request::create('/account')));
        self::assertTrue($matcher->matches(Request::create('/projects')));
    }
}
