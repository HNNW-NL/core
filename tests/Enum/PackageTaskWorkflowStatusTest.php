<?php

namespace App\Tests\Enum;

use App\Enum\PackageTaskWorkflowStatus;
use PHPUnit\Framework\TestCase;

final class PackageTaskWorkflowStatusTest extends TestCase
{
    public function testWorkflowAcceptsOnlyTheDefinedForwardTransitions(): void
    {
        self::assertTrue(PackageTaskWorkflowStatus::OPEN->canTransitionTo(PackageTaskWorkflowStatus::ASSIGNED));
        self::assertTrue(PackageTaskWorkflowStatus::SUBMITTED->canTransitionTo(PackageTaskWorkflowStatus::ACCEPTED));
        self::assertTrue(PackageTaskWorkflowStatus::SUBMITTED->canTransitionTo(PackageTaskWorkflowStatus::CHANGES_REQUESTED));
        self::assertTrue(PackageTaskWorkflowStatus::CHANGES_REQUESTED->canTransitionTo(PackageTaskWorkflowStatus::IN_PROGRESS));

        self::assertFalse(PackageTaskWorkflowStatus::OPEN->canTransitionTo(PackageTaskWorkflowStatus::SUBMITTED));
        self::assertFalse(PackageTaskWorkflowStatus::ACCEPTED->canTransitionTo(PackageTaskWorkflowStatus::IN_PROGRESS));
        self::assertFalse(PackageTaskWorkflowStatus::CHANGES_REQUESTED->canTransitionTo(PackageTaskWorkflowStatus::SUBMITTED));
    }

    public function testLegacyActiveStatusMapsToOpen(): void
    {
        self::assertSame(
            PackageTaskWorkflowStatus::OPEN,
            PackageTaskWorkflowStatus::fromStoredName('active'),
        );
        self::assertSame(
            PackageTaskWorkflowStatus::IN_PROGRESS,
            PackageTaskWorkflowStatus::fromStoredName('IN PROGRESS'),
        );
        self::assertSame(
            PackageTaskWorkflowStatus::ASSIGNED,
            PackageTaskWorkflowStatus::fromStoredName('toegewezen'),
        );
        self::assertSame(
            PackageTaskWorkflowStatus::IN_PROGRESS,
            PackageTaskWorkflowStatus::fromStoredName('in-progress'),
        );
        self::assertNull(PackageTaskWorkflowStatus::fromStoredName('unknown'));
    }
}
