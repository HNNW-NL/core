<?php

namespace App\Tests\Module\Main\DTO;

use App\Entity\Common\Status;
use App\Entity\Project\PackageTask;
use App\Entity\Project\WorkPackage;
use App\Module\Main\DTO\WorkPackageOverview;
use PHPUnit\Framework\TestCase;

final class WorkPackageOverviewTest extends TestCase
{
    public function testCompletedTaskCountAndTaskWorkPackageRelationship(): void
    {
        $workPackage = new WorkPackage();
        $completedTask = (new PackageTask())->setTitle('Afgeronde taak');
        $completedTask->setStatus((new Status())->setName('gereed'));
        $openTask = (new PackageTask())->setTitle('Open taak');
        $openTask->setStatus((new Status())->setName('open'));
        $anotherOpenTask = (new PackageTask())->setTitle('Nog een open taak');
        $anotherOpenTask->setStatus((new Status())->setName('open'));

        $workPackage->addWorkPackageTask($completedTask);
        $workPackage->addWorkPackageTask($openTask);
        $workPackage->addWorkPackageTask($anotherOpenTask);

        $overview = new WorkPackageOverview(
            $workPackage,
            $workPackage->getPackageTasks()->toArray(),
        );

        self::assertSame(1, $overview->getCompletedTaskCount());
        self::assertCount(3, $overview->packageTasks);
        self::assertSame($workPackage, $completedTask->getWorkPackage());
    }
}
