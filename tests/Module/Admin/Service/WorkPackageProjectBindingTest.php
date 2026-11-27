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
use App\Module\Admin\Service\WorkPackageCrudService as AdminWorkPackageCrudService;
use App\Module\Main\Service\WorkPackageCrudService;
use App\Module\Org\Handler\CreateWorkPackageHandler as OrgCreateWorkPackageHandler;
use App\Module\Org\Handler\ManageProjectWorkPackagesHandler;
use App\Repository\Common\StatusRepository;
use App\Security\Permission;
use App\Security\ProjectPermission;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\HttpFoundation\InputBag;

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

    #[DataProvider('invalidInputProvider')]
    public function testEveryWritePathValidatesBeforeChangingStoredData(string $entry, string $operation, string $title, string $slug, string $message): void
    {
        [$em, , $project, $package] = $this->prepareEditor();
        $before = $em->getConnection()->fetchAllAssociative('SELECT * FROM work_packages ORDER BY id');
        try {
            $this->writePackage($entry, $operation, $project, $package, $title, $slug);
            self::fail('Invalid work package input must be rejected before persistence.');
        } catch (\InvalidArgumentException $exception) {
            self::assertStringContainsString($message, $exception->getMessage());
        }
        // Also prove that a later flush cannot save a partially modified entity.
        $em->flush();
        self::assertSame($before, $em->getConnection()->fetchAllAssociative('SELECT * FROM work_packages ORDER BY id'));
    }

    public static function invalidInputProvider(): iterable
    {
        foreach (self::writePathProvider() as [$entry, $operation]) {
            foreach ([
                'missing title' => ['', 'flow03-valid', 'titel'],
                'blank title' => ["\u{00A0}\u{2003}", 'flow03-valid', 'titel'],
                'long title' => [str_repeat('é', 256), 'flow03-valid', 'titel'],
                'missing slug' => ['Work package', '', 'slug'],
                'invalid slug' => ['Work package', 'Invalid Slug', 'slug'],
                'long slug' => ['Work package', str_repeat('a', 256), 'slug'],
            ] as $case => $input) {
                yield $entry . ' / ' . $operation . ' / ' . $case => [$entry, $operation, ...$input];
            }
        }
    }

    #[DataProvider('writePathProvider')]
    public function testEveryWritePathNormalisesValidInput(string $entry, string $operation): void
    {
        [$em, , $project, $package] = $this->prepareEditor();
        $this->writePackage($entry, $operation, $project, $package, '  Normalised package  ', '  flow03-normalised  ');
        $em->clear();
        $saved = $em->getRepository(WorkPackage::class)->findOneBy(['slug' => 'flow03-normalised']);
        self::assertInstanceOf(WorkPackage::class, $saved);
        self::assertSame('Normalised package', $saved->getTitle());
        self::assertEquals($project->getId(), $saved->getProject()->getId());
    }

    #[DataProvider('duplicateSlugProvider')]
    public function testEveryWritePathRejectsDuplicateAndArchivedSlugs(string $entry, string $operation, bool $archived): void
    {
        [$em, , $project, $package] = $this->prepareEditor();
        $existing = (new WorkPackage())->setProject($project)->setStatus($package->getStatus())
            ->setTitle('Reserved package')->setSlug('flow03-reserved');
        if ($archived) {
            $existing->softDelete();
        }
        $em->persist($existing);
        $em->flush();
        $before = $em->getConnection()->fetchAllAssociative('SELECT * FROM work_packages ORDER BY id');
        try {
            $this->writePackage($entry, $operation, $project, $package, 'Changed title', ' flow03-reserved ');
            self::fail('Work package slugs must remain unique within the project.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('Deze slug wordt al gebruikt binnen dit project.', $exception->getMessage());
        }
        $em->flush();
        self::assertSame($before, $em->getConnection()->fetchAllAssociative('SELECT * FROM work_packages ORDER BY id'));
    }

    public static function duplicateSlugProvider(): iterable
    {
        foreach (self::writePathProvider() as [$entry, $operation]) {
            yield $entry . ' / ' . $operation . ' / active' => [$entry, $operation, false];
            yield $entry . ' / ' . $operation . ' / archived' => [$entry, $operation, true];
        }
    }

    #[DataProvider('writePathProvider')]
    public function testSlugUniquenessIsScopedToTheActualProject(string $entry, string $operation): void
    {
        [$em, , $project, $package] = $this->prepareEditor();
        $other = (new Project())->setOwnerAccount($project->getOwnerAccount())
            ->setOwnerOrganisation($project->getOwnerOrganisation())->setStatus($project->getStatus())
            ->setTitle('Other slug project')->setSlug('flow03-slug-other')->setVisibility('private')
            ->setSummary('Other')->setDescription('Other')->setStartDate(new \DateTimeImmutable())->setCapacity(5);
        $em->persist($other);
        $em->persist((new WorkPackage())->setProject($other)->setStatus($package->getStatus())
            ->setTitle('Other project package')->setSlug('flow03-scoped'));
        $em->flush();
        $this->writePackage($entry, $operation, $project, $package, 'Own project package', 'flow03-scoped');
        self::assertSame(2, $em->getRepository(WorkPackage::class)->count(['slug' => 'flow03-scoped']));
    }

    #[DataProvider('legacySlugProvider')]
    public function testUpdatingAnUnchangedLegacySlugRemainsSupported(string $slug): void
    {
        [$em, $service, $project, $package] = $this->prepareEditor();
        $package->setSlug($slug);
        $em->flush();
        $service->update(new UpdateWorkPackageDTO((string) $package->getId(), 'Updated', $slug, '', null), $project);
        self::assertSame($slug, $package->getSlug());
        self::assertSame('Updated', $package->getTitle());
    }

    public static function legacySlugProvider(): iterable
    {
        yield 'legacy punctuation' => ['Legacy_WorkPackage'];
        yield 'legacy multibyte characters' => [str_repeat('é', 200)];
    }

    #[DataProvider('slugWriteOperationProvider')]
    public function testSlugWritesExcludeConcurrentProjectWriters(string $operation): void
    {
        [$em, $service, $project, $package] = $this->prepareEditor();
        $this->operate($service, $operation, $project, $package, $project);

        // A genuinely separate connection must not share DAMA's test transaction.
        $params = $em->getConnection()->getParams();
        unset($params['driverClass'], $params['wrapperClass']);
        $otherConnection = DriverManager::getConnection($params);
        $otherConnection->beginTransaction();
        try {
            // Even a shared row lock must fail while the first writer holds the project lock.
            $otherConnection->executeQuery('SELECT id FROM projects WHERE id = ? FOR SHARE NOWAIT', [$project->getId()->toRfc4122()]);
            self::fail('Concurrent requests must not check slug availability simultaneously.');
        } catch (DriverException $exception) {
            self::assertSame('55P03', $exception->getSQLState());
        } finally {
            $otherConnection->rollBack();
            $otherConnection->close();
        }
    }

    public static function slugWriteOperationProvider(): iterable
    {
        yield 'create' => ['create'];
        yield 'update' => ['update'];
    }

    public static function writePathProvider(): iterable
    {
        foreach (['shared service', 'admin service', 'admin handler', 'org management handler'] as $entry) {
            foreach (['create', 'update'] as $operation) {
                yield $entry . ' / ' . $operation => [$entry, $operation];
            }
        }
        yield 'org create handler' => ['org create handler', 'create'];
    }

    private function writePackage(string $entry, string $operation, Project $project, WorkPackage $package, string $title, string $slug): void
    {
        $container = static::getContainer();
        $service = $container->get(WorkPackageCrudService::class);
        $create = new CreateWorkPackageDTO((string) $project->getId(), $title, $slug, 'Description', null);
        $update = new UpdateWorkPackageDTO((string) $package->getId(), $title, $slug, 'Description', null);
        if ($entry === 'admin service') {
            $service = new AdminWorkPackageCrudService(
                $container->get(EntityManagerInterface::class), $container->get(StatusRepository::class),
                $container->get(AuthorizationCheckerInterface::class),
            );
        }
        match ($entry) {
            'shared service', 'admin service' => $operation === 'create' ? $service->create($create) : $service->update($update),
            'admin handler' => $operation === 'create' ? (new CreateWorkPackageHandler($service))->handle($create) : (new UpdateWorkPackageHandler($service))->handle($update),
            'org create handler' => (new OrgCreateWorkPackageHandler($service))->handle($create),
            'org management handler' => $container->get(ManageProjectWorkPackagesHandler::class)->handle($project, new InputBag([
                '_action' => $operation === 'create' ? 'create_work_package' : 'update_work_package',
                'workPackageId' => (string) $package->getId(), 'title' => $title, 'slug' => $slug, 'description' => 'Description',
            ])),
        };
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
