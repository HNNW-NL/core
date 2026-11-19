<?php

namespace App\Tests\Module\Main\Handler;

use App\Entity\Common\Status;
use App\Entity\Project\Project;
use App\Entity\Project\WorkPackage;
use App\Module\Main\DTO\ChangeWorkPackageStatusDTO;
use App\Module\Main\Handler\ChangeWorkPackageStatusHandler;
use App\Module\Main\Service\WorkPackageWorkflowService;
use App\Security\Voter\WorkPackageVoter;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Uid\Ulid;

class ChangeWorkPackageStatusHandlerTest extends TestCase
{
    public function testAllowedTransitionUpdatesStatusAndFlushes(): void
    {
        $project = new Project();
        $workPackage = (new WorkPackage())->setProject($project);
        $workPackage->setStatus((new Status())->setName('Open')->setScope('work_package'));
        $newStatus = (new Status())->setName('Assigned')->setScope('work_package');
        $workPackageId = (new Ulid())->toBase32();

        $statusRepository = $this->createMock(EntityRepository::class);
        $statusRepository->expects(self::once())
            ->method('findOneBy')
            ->with(['name' => 'Assigned', 'scope' => 'work_package'])
            ->willReturn($newStatus);

        $entityManager = $this->transactionalEntityManager();
        $entityManager->expects(self::once())
            ->method('find')
            ->with(WorkPackage::class, self::isInstanceOf(Ulid::class), LockMode::PESSIMISTIC_WRITE)
            ->willReturn($workPackage);
        $entityManager->expects(self::once())
            ->method('getRepository')
            ->with(Status::class)
            ->willReturn($statusRepository);
        $entityManager->expects(self::once())->method('flush');

        $handler = new ChangeWorkPackageStatusHandler(
            new WorkPackageWorkflowService(),
            $entityManager,
            $this->expectEditDecision($workPackage, true),
        );

        $handler->handle(new ChangeWorkPackageStatusDTO(
            $workPackageId,
            'Assigned',
        ), $project);

        self::assertSame($newStatus, $workPackage->getStatus());
    }

    public function testDisallowedTransitionDoesNotUpdateStatusOrFlush(): void
    {
        $currentStatus = (new Status())->setName('Draft')->setScope('work_package');
        $project = new Project();
        $workPackage = (new WorkPackage())->setProject($project);
        $workPackage->setStatus($currentStatus);
        $newStatus = (new Status())->setName('Closed')->setScope('work_package');
        $workPackageId = (new Ulid())->toBase32();

        $statusRepository = $this->createMock(EntityRepository::class);
        $statusRepository->expects(self::once())
            ->method('findOneBy')
            ->with(['name' => 'Closed', 'scope' => 'work_package'])
            ->willReturn($newStatus);

        $entityManager = $this->transactionalEntityManager();
        $entityManager->expects(self::once())
            ->method('find')
            ->with(WorkPackage::class, self::isInstanceOf(Ulid::class), LockMode::PESSIMISTIC_WRITE)
            ->willReturn($workPackage);
        $entityManager->expects(self::once())
            ->method('getRepository')
            ->with(Status::class)
            ->willReturn($statusRepository);
        $entityManager->expects(self::never())->method('flush');

        $handler = new ChangeWorkPackageStatusHandler(
            new WorkPackageWorkflowService(),
            $entityManager,
            $this->expectEditDecision($workPackage, true),
        );

        try {
            $handler->handle(new ChangeWorkPackageStatusDTO(
                $workPackageId,
                'Closed',
            ), $project);
            self::fail('Expected the handler to reject the disallowed transition.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame(
                'Cannot change work package status from "Draft" to "Closed".',
                $exception->getMessage(),
            );
        }

        self::assertSame($currentStatus, $workPackage->getStatus());
    }

    public function testDeniedUserCannotChangeStatus(): void
    {
        $project = new Project();
        $workPackage = (new WorkPackage())->setProject($project);
        $currentStatus = (new Status())->setName('Open')->setScope('work_package');
        $workPackage->setStatus($currentStatus);
        $entityManager = $this->transactionalEntityManager();
        $entityManager->method('find')->willReturn($workPackage);
        $entityManager->expects(self::never())->method('getRepository');
        $entityManager->expects(self::never())->method('flush');
        $handler = new ChangeWorkPackageStatusHandler(
            new WorkPackageWorkflowService(),
            $entityManager,
            $this->expectEditDecision($workPackage, false),
        );

        $this->expectException(AccessDeniedException::class);
        $handler->handle(new ChangeWorkPackageStatusDTO((new Ulid())->toBase32(), 'Assigned'), $project);
    }

    public function testRejectsCurrentStatusFromAnotherScope(): void
    {
        $project = new Project();
        $workPackage = (new WorkPackage())->setProject($project);
        $workPackage->setStatus((new Status())->setName('Open')->setScope('project'));
        $entityManager = $this->transactionalEntityManager();
        $entityManager->method('find')->willReturn($workPackage);
        $entityManager->expects(self::never())->method('getRepository');
        $entityManager->expects(self::never())->method('flush');
        $handler = new ChangeWorkPackageStatusHandler(
            new WorkPackageWorkflowService(),
            $entityManager,
            $this->expectEditDecision($workPackage, true),
        );

        $this->expectExceptionMessage('Current status is outside the work package scope.');
        $handler->handle(new ChangeWorkPackageStatusDTO((new Ulid())->toBase32(), 'Assigned'), $project);
    }

    public function testRejectsWorkPackageFromAnotherProjectBeforeAuthorization(): void
    {
        $workPackage = (new WorkPackage())->setProject(new Project());
        $entityManager = $this->transactionalEntityManager();
        $entityManager->method('find')->willReturn($workPackage);
        $entityManager->expects(self::never())->method('flush');
        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->expects(self::never())->method('isGranted');
        $handler = new ChangeWorkPackageStatusHandler(
            new WorkPackageWorkflowService(),
            $entityManager,
            $authorizationChecker,
        );

        $this->expectExceptionMessage('Work package not found in this project.');
        $handler->handle(new ChangeWorkPackageStatusDTO((new Ulid())->toBase32(), 'Assigned'), new Project());
    }

    public function testRejectsSoftDeletedWorkPackage(): void
    {
        $project = new Project();
        $workPackage = (new WorkPackage())->setProject($project);
        $workPackage->softDelete();
        $entityManager = $this->transactionalEntityManager();
        $entityManager->method('find')->willReturn($workPackage);
        $entityManager->expects(self::never())->method('flush');
        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->expects(self::never())->method('isGranted');
        $handler = new ChangeWorkPackageStatusHandler(
            new WorkPackageWorkflowService(),
            $entityManager,
            $authorizationChecker,
        );

        $this->expectExceptionMessage('Work package not found in this project.');
        $handler->handle(new ChangeWorkPackageStatusDTO((new Ulid())->toBase32(), 'Assigned'), $project);
    }

    private function transactionalEntityManager(): EntityManagerInterface
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('transactional')->willReturnCallback(static fn (\Closure $operation) => $operation());
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);

        return $entityManager;
    }

    private function expectEditDecision(WorkPackage $workPackage, bool $granted): AuthorizationCheckerInterface
    {
        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->expects(self::once())
            ->method('isGranted')
            ->with(WorkPackageVoter::EDIT, self::identicalTo($workPackage))
            ->willReturn($granted);

        return $authorizationChecker;
    }
}