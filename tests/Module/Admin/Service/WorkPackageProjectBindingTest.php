<?php

namespace App\Tests\Module\Admin\Service;

use App\Entity\Account\Account;
use App\Entity\Org\OrgMember;
use App\Entity\Org\OrgRole;
use App\Entity\Project\Project;
use App\Entity\Project\ProjectParticipant;
use App\Entity\Project\WorkPackage;
use App\Kernel;
use App\Module\Admin\DTO\CreateWorkPackageDTO;
use App\Module\Admin\DTO\UpdateWorkPackageDTO;
use App\Module\Admin\Handler\CreateWorkPackageHandler;
use App\Module\Admin\Handler\DeleteWorkPackageHandler;
use App\Module\Admin\Handler\UpdateWorkPackageHandler;
use App\Module\Main\Service\WorkPackageCrudService;
use App\Security\Permission;
use App\Security\ProjectPermission;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class WorkPackageProjectBindingTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    #[DataProvider('operationProvider')]
    public function testAnotherProjectContextIsRejected(string $operation): void
    {
        [$em, $service, $project, $package] = $this->prepareEditor();
        $other = (new Project())->setOwnerAccount($project->getOwnerAccount())
            ->setOwnerOrganisation($project->getOwnerOrganisation())->setStatus($project->getStatus())
            ->setTitle('FLOW-03 other')->setSlug('flow03-other')->setVisibility('private')
            ->setSummary('Other')->setDescription('Other')->setStartDate(new \DateTimeImmutable())->setCapacity(5);
        $em->persist($other);
        $em->flush();
        $snapshot = $em->getConnection()->fetchAllAssociative('SELECT * FROM work_packages ORDER BY id');

        try {
            $this->operate($service, $operation, $project, $package, $other);
            self::fail('A mismatched project context must be rejected.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('Werkpakket hoort niet bij dit project.', $exception->getMessage());
        }
        self::assertSame($snapshot, $em->getConnection()->fetchAllAssociative('SELECT * FROM work_packages ORDER BY id'));
    }

    #[DataProvider('deniedRightsProvider')]
    public function testDirectServiceCallsRequireProjectAndOrganisationRights(string $operation, string $missingRight): void
    {
        [$em, $service, $project, $package] = $this->prepareEditor();
        if ($missingRight === 'organisation') {
            $role = $em->getRepository(OrgRole::class)->findOneBy(['name' => 'FLOW-03 editor']);
            $role->setPermissionsMask(0);
            $em->flush();
        } elseif ($missingRight === 'project') {
            $project->setOwnerAccount($em->getRepository(Account::class)->findOneBy(['username' => 'AdminUser']));
            $em->flush();
        } else {
            $outsider = $em->getRepository(Account::class)->findOneBy(['username' => 'AdminUser']);
            static::getContainer()->get('security.token_storage')->setToken(new UsernamePasswordToken($outsider, 'main', $outsider->getRoles()));
        }
        $snapshot = $em->getConnection()->fetchAllAssociative('SELECT * FROM work_packages ORDER BY id');
        try {
            $this->operate($service, $operation, $project, $package, $project);
            self::fail('An unauthorised actor must be rejected.');
        } catch (AccessDeniedException) {
            self::assertSame($snapshot, $em->getConnection()->fetchAllAssociative('SELECT * FROM work_packages ORDER BY id'));
        }
    }

    #[DataProvider('operationProvider')]
    public function testDeletedProjectsCannotBeModified(string $operation): void
    {
        [$em, $service, $project, $package] = $this->prepareEditor();
        $project->softDelete();
        $em->flush();
        $snapshot = $em->getConnection()->fetchAllAssociative('SELECT * FROM work_packages ORDER BY id');
        try {
            $this->operate($service, $operation, $project, $package, $project);
            self::fail('A deleted project must be rejected.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('Project is verwijderd.', $exception->getMessage());
        }
        self::assertSame($snapshot, $em->getConnection()->fetchAllAssociative('SELECT * FROM work_packages ORDER BY id'));
    }

    public function testExistingHandlersPreserveTheProjectDuringCRUD(): void
    {
        [$em, $service, $project] = $this->prepareEditor();
        $package = (new CreateWorkPackageHandler($service))->handle(
            new CreateWorkPackageDTO((string) $project->getId(), 'FLOW-03 created', 'flow03-created', '', null),
        );
        self::assertSame($project, $package->getProject());
        $updated = (new UpdateWorkPackageHandler($service))->handle(
            new UpdateWorkPackageDTO((string) $package->getId(), 'FLOW-03 updated', 'flow03-created', 'Updated', null),
        );
        self::assertSame($project, $updated->getProject());
        self::assertSame('FLOW-03 updated', $updated->getTitle());
        (new DeleteWorkPackageHandler($service))->handle((string) $package->getId());
        self::assertNotNull($package->getDeletedAt());
        $orgCreated = (new \App\Module\Org\Handler\CreateWorkPackageHandler($service))->handle(
            new CreateWorkPackageDTO((string) $project->getId(), 'FLOW-03 organisation', 'flow03-org', '', null),
        );
        $em->clear();
        self::assertEquals($project->getId(), $em->find(WorkPackage::class, $orgCreated->getId())->getProject()->getId());
    }

    public function testProjectEditRightsDoNotGrantWorkPackageDeletion(): void
    {
        [$em, $service, $project, $package] = $this->prepareEditor();
        $editor = $project->getOwnerAccount();
        $project->setOwnerAccount($em->getRepository(Account::class)->findOneBy(['username' => 'AdminUser']));
        $participant = $em->getRepository(ProjectParticipant::class)->findOneBy(['project' => $project, 'profile' => $editor->getProfile()]);
        $participant->join();
        $participant->getRole()->setPermissionsMask(ProjectPermission::VIEW->value | ProjectPermission::EDIT->value);
        $em->flush();

        $this->expectException(AccessDeniedException::class);
        try {
            $service->delete((string) $package->getId(), $project);
        } finally {
            self::assertNull($package->getDeletedAt());
        }
    }

    public static function operationProvider(): iterable
    {
        yield 'create' => ['create'];
        yield 'update' => ['update'];
        yield 'delete' => ['delete'];
    }

    public static function deniedRightsProvider(): iterable
    {
        foreach (['create', 'update', 'delete'] as $operation) {
            foreach (['organisation', 'project', 'both'] as $missingRight) {
                yield $operation . ' without ' . $missingRight . ' rights' => [$operation, $missingRight];
            }
        }
    }

    private function operate(WorkPackageCrudService $service, string $operation, Project $target, WorkPackage $package, Project $context): void
    {
        match ($operation) {
            'create' => $service->create(new CreateWorkPackageDTO((string) $target->getId(), 'FLOW-03', 'flow03-test', '', null), $context),
            'update' => $service->update(new UpdateWorkPackageDTO((string) $package->getId(), 'Tampered', 'flow03-test', '', null), $context),
            'delete' => $service->delete((string) $package->getId(), $context),
        };
    }

    /** @return array{EntityManagerInterface, WorkPackageCrudService, Project, WorkPackage} */
    private function prepareEditor(): array
    {
        static::bootKernel(['debug' => false]);
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $account = $em->getRepository(Account::class)->findOneBy(['username' => 'TestUser']);
        $project = $em->getRepository(Project::class)->findOneBy(['slug' => 'Test-Project']);
        $role = (new OrgRole())->setName('FLOW-03 editor')->setOrganisation($project->getOwnerOrganisation())
            ->setPermissionsMask(Permission::ORG_MANAGE_PROJECTS->value);
        $em->persist($role);
        $em->persist((new OrgMember())->setAccount($account)->setOrganisation($project->getOwnerOrganisation())->setOrgRole($role));
        $em->flush();
        $container->get('security.token_storage')->setToken(new UsernamePasswordToken($account, 'main', $account->getRoles()));
        return [$em, $container->get(WorkPackageCrudService::class), $project, $em->getRepository(WorkPackage::class)->findOneBy(['project' => $project])];
    }
}
