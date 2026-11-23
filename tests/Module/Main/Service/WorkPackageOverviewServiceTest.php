<?php

namespace App\Tests\Module\Main\Service;

use App\Entity\Project\PackageTask;
use App\Entity\Project\WorkPackage;
use App\Module\Main\Service\WorkPackageOverviewService;
use App\Repository\Project\PackageTaskRepository;
use App\Repository\Project\ProjectWorkPackageRepository;
use PHPUnit\Framework\TestCase;

final class WorkPackageOverviewServiceTest extends TestCase
{
    public function testGroupsVisibleTasksByWorkPackageAndSkipsOrphanTasks(): void
    {
        $firstWorkPackage = new WorkPackage();
        $secondWorkPackage = new WorkPackage();
        $firstTask = (new PackageTask())->setWorkPackage($firstWorkPackage);
        $secondTask = (new PackageTask())->setWorkPackage($firstWorkPackage);
        $orphanTask = new PackageTask();

        $workPackageRepository = $this->createMock(ProjectWorkPackageRepository::class);
        $workPackageRepository->expects(self::once())
            ->method('findVisibleForProjectSlug')
            ->with('sample-project')
            ->willReturn([$firstWorkPackage, $secondWorkPackage]);

        $taskRepository = $this->createMock(PackageTaskRepository::class);
        $taskRepository->expects(self::once())
            ->method('findVisibleForProjectSlug')
            ->with('sample-project')
            ->willReturn([$firstTask, $secondTask, $orphanTask]);

        $service = new WorkPackageOverviewService($workPackageRepository, $taskRepository);

        $overviews = $service->getForProjectSlug('sample-project');

        self::assertCount(2, $overviews);
        self::assertSame($firstWorkPackage, $overviews[0]->workPackage);
        self::assertSame([$firstTask, $secondTask], $overviews[0]->packageTasks);
        self::assertSame($secondWorkPackage, $overviews[1]->workPackage);
        self::assertSame([], $overviews[1]->packageTasks);
    }

    public function testReturnsEmptyListWhenProjectHasNoVisibleWorkPackages(): void
    {
        $workPackageRepository = $this->createMock(ProjectWorkPackageRepository::class);
        $workPackageRepository->expects(self::once())
            ->method('findVisibleForProjectSlug')
            ->with('empty-project')
            ->willReturn([]);

        $taskRepository = $this->createMock(PackageTaskRepository::class);
        $taskRepository->expects(self::once())
            ->method('findVisibleForProjectSlug')
            ->with('empty-project')
            ->willReturn([]);

        $service = new WorkPackageOverviewService($workPackageRepository, $taskRepository);

        self::assertSame([], $service->getForProjectSlug('empty-project'));
    }
}
