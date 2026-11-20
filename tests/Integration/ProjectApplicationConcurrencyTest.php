<?php

namespace App\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class ProjectApplicationConcurrencyTest extends TestCase
{
    public function testOverlappingSubmissionsCreateOneApplication(): void
    {
        // Eigen processen gebruiken onafhankelijke verbindingen en echte commits.
        $process = new Process([PHP_BINARY, __DIR__.'/project_application_concurrency.php'], dirname(__DIR__, 2));
        $process->setTimeout(30);
        $process->run();

        self::assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
        self::assertStringContainsString('database bevat precies 1 aanvraag', $process->getOutput());
    }
}
