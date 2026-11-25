<?php

namespace App\Tests\Module\Main\Service;

use App\Module\Main\Service\WorkPackageWorkflowService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WorkPackageWorkflowServiceTest extends TestCase
{
    #[DataProvider('statusProvider')]
    public function testAllowedTransitions(string $status, array $allowed): void
    {
        $workflow = new WorkPackageWorkflowService();

        self::assertSame($allowed, $workflow->getAllowedTransitions($status));
        foreach ($workflow->getStatusNames() as $target) {
            self::assertSame(in_array($target, $allowed, true), $workflow->canTransition($status, $target));
        }
        self::assertFalse($workflow->canTransition($status, 'Unknown'));
    }

    public static function statusProvider(): iterable
    {
        yield 'draft' => ['Draft', ['Planned', 'Cancelled']];
        yield 'planned' => ['Planned', ['Open', 'Cancelled']];
        yield 'open' => ['Open', ['Assigned', 'Cancelled']];
        yield 'assigned' => ['Assigned', ['Closed', 'Cancelled']];
        yield 'closed' => ['Closed', []];
        yield 'cancelled' => ['Cancelled', []];
        yield 'unknown' => ['Unknown', []];
    }
}
