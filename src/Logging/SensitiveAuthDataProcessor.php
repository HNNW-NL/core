<?php

namespace App\Logging;

use Monolog\Attribute\AsMonologProcessor;
use Monolog\LogRecord;
use Symfony\Component\HttpFoundation\RequestStack;

#[AsMonologProcessor(priority: -1000)]
final class SensitiveAuthDataProcessor
{
    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $path = $this->requestStack->getMainRequest()?->getPathInfo() ?? '';
        if (!preg_match('~^/auth/(?:reset-password|verify-email)/~', $path)) {
            return $record;
        }

        return $record->with(
            message: $this->redact($record->message),
            context: $this->redact($record->context),
            extra: $this->redact($record->extra),
        );
    }

    private function redact(mixed $value, int $depth = 0): mixed
    {
        if ($depth > 8) {
            return '[redacted]';
        }
        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $item) {
                $result[$key] = is_string($key) && preg_match('/token|password|email|username|cookie|authorization|referer|received|params|parameters/i', $key)
                    ? '[redacted]'
                    : $this->redact($item, $depth + 1);
            }

            return $result;
        }
        if (is_string($value)) {
            $value = preg_replace('~(/auth/(?:reset-password|verify-email)/)[^/\s"\'?#<>]+/[^/\s"\'?#<>]+(?:\?[^\s"\'<>]*)?~i', '$1[redacted]/[redacted]', $value);
            $value = preg_replace('~\b[0-9a-f]{64}\b~i', '[redacted-token]', $value);

            return preg_replace('~[^\s<>"\'/:]+@[^\s<>"\'/?#]+~', '[redacted-email]', $value);
        }
        if (is_object($value) && !$value instanceof \DateTimeInterface) {
            return ['type' => $value::class];
        }

        return $value;
    }
}
