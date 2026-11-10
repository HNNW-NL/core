<?php

namespace App\Tests\Module\Main\Handler;

use App\Entity\Common\Status;
use App\Entity\Project\WorkPackage;
use App\Module\Main\DTO\ChangeWorkPackageStatusDTO;
use App\Module\Main\Handler\ChangeWorkPackageStatusHandler;
use App\Module\Main\Service\WorkPackageWorkflowService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class ChangeWorkPackageStatusHandlerTest extends TestCase
{
    public function testAllowedTransitionUpdatesStatusAndFlushes(): void
    {
        $workPackage = new WorkPackage();
        $workPackage->setStatus((new Status())->setName('Open'));
        $newStatus = (new Status())->setName('Assigned');

        $statusRepository = $this->createMock(EntityRepository::class);
        $statusRepository->expects(self::once())
            ->method('findOneBy')
            ->with(['name' => 'Assigned', 'scope' => 'work_package'])
            ->willReturn($newStatus);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())
            ->method('find')
            ->with(WorkPackage::class, self::isInstanceOf(Uuid::class))
            ->willReturn($workPackage);
        $entityManager->expects(self::once())
            ->method('getRepository')
            ->with(Status::class)
            ->willReturn($statusRepository);
        $entityManager->expects(self::once())->method('flush');

        $handler = new ChangeWorkPackageStatusHandler(
            new WorkPackageWorkflowService(),
            $entityManager,
        );

        $handler->handle(new ChangeWorkPackageStatusDTO(
            $workPackage->getId()->toRfc4122(),
            'Assigned',
        ));

        self::assertSame($newStatus, $workPackage->getStatus());
    }

    public function testDisallowedTransitionDoesNotUpdateStatusOrFlush(): void
    {
        $currentStatus = (new Status())->setName('Draft');
        $workPackage = new WorkPackage();
        $workPackage->setStatus($currentStatus);
        $newStatus = (new Status())->setName('Closed');

        $statusRepository = $this->createMock(EntityRepository::class);
        $statusRepository->expects(self::once())
            ->method('findOneBy')
            ->with(['name' => 'Closed', 'scope' => 'work_package'])
            ->willReturn($newStatus);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())
            ->method('find')
            ->with(WorkPackage::class, self::isInstanceOf(Uuid::class))
            ->willReturn($workPackage);
        $entityManager->expects(self::once())
            ->method('getRepository')
            ->with(Status::class)
            ->willReturn($statusRepository);
        $entityManager->expects(self::never())->method('flush');

        $handler = new ChangeWorkPackageStatusHandler(
            new WorkPackageWorkflowService(),
            $entityManager,
        );

        try {
            $handler->handle(new ChangeWorkPackageStatusDTO(
                $workPackage->getId()->toRfc4122(),
                'Closed',
            ));
            self::fail('Expected the handler to reject the disallowed transition.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame(
                'Cannot change work package status from "Draft" to "Closed".',
                $exception->getMessage(),
            );
        }

        self::assertSame($currentStatus, $workPackage->getStatus());
    }
}