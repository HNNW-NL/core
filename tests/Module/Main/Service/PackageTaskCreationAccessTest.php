<?php

namespace App\Tests\Module\Main\Service;

use App\Entity\Account\Account;
use App\Entity\Admin\Admin;
use App\Entity\Admin\AdminRole;
use App\Entity\Common\Status;
use App\Entity\Org\Organisation;
use App\Entity\Org\OrgMember;
use App\Entity\Org\OrgRole;
use App\Entity\Project\PackageTask;
use App\Entity\Project\Project;
use App\Entity\Project\ProjectParticipant;
use App\Entity\Project\WorkPackage;
use App\Kernel;
use App\Module\Admin\DTO\CreatePackageTaskDTO;
use App\Module\Admin\Handler\CreatePackageTaskHandler;
use App\Module\Admin\Service\PackageTaskCrudService as AdminPackageTaskCrudService;
use App\Module\Main\Service\PackageTaskCrudService;
use App\Module\Main\Service\PackageTaskInputValidator;
use App\Module\Org\Handler\ManageProjectWorkPackagesHandler;
use App\Module\Org\Service\ProjectWriteLock;
use App\Repository\Common\StatusRepository;
use App\Security\Permission;
use App\Security\ProjectPermission;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class PackageTaskCreationAccessTest extends KernelTestCase
{
    private const VALID_DOD = 'Alle drie bronnen zijn gecontroleerd en het overzicht bevat bronverwijzingen.';

    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    #[DataProvider('deniedCallerProvider')]
    public function testAllCreationPathsRejectUnauthorisedActors(string $entry, string $actor): void
    {
        [$em, $project, $package] = $this->prepareActor($actor);
        $before = $em->getConnection()->fetchAllAssociative('SELECT * FROM package_tasks ORDER BY id');
        try {
            $this->createTask($entry, new CreatePackageTaskDTO((string) $package->getId(), 'Rejected', 'access-rejected', self::VALID_DOD, null, 'medium'));
            self::fail('Task creation must require project, work package and organisation rights.');
        } catch (AccessDeniedException) {
            self::assertSame($before, $em->getConnection()->fetchAllAssociative('SELECT * FROM package_tasks ORDER BY id'));
        }
    }

    #[DataProvider('allowedCallerProvider')]
    public function testExistingCreationPathsStillWorkForAuthorisedActors(string $entry, string $actor): void
    {
        [$em, $project, $package] = $this->prepareActor($actor);
        $this->createTask($entry, new CreatePackageTaskDTO((string) $package->getId(), 'Allowed', 'access-allowed', " \n" . self::VALID_DOD . "\t ", null, 'medium'));
        $em->clear();
        $task = $em->getRepository(PackageTask::class)->findOneBy(['slug' => 'access-allowed']);
        self::assertInstanceOf(PackageTask::class, $task);
        self::assertEquals($package->getId(), $task->getWorkPackage()->getId());
        self::assertEquals($project->getId(), $task->getWorkPackage()->getProject()->getId());
        self::assertSame(1, $em->getRepository(PackageTask::class)->count(['slug' => 'access-allowed']));
        self::assertSame(self::VALID_DOD, $task->getDescription());
    }

    #[DataProvider('entryProvider')]
    public function testExplicitProjectContextCannotBeReplaced(string $entry): void
    {
        [$em, $project, $package] = $this->prepareActor('owner');
        $other = (new Project())->setOwnerAccount($project->getOwnerAccount())
            ->setOwnerOrganisation($project->getOwnerOrganisation())->setStatus($project->getStatus())
            ->setTitle('Other task project')->setSlug('access-other')->setVisibility('private')
            ->setSummary('Other')->setDescription('Other')->setStartDate(new \DateTimeImmutable())->setCapacity(5);
        $em->persist($other);
        $em->flush();
        $before = $em->getConnection()->fetchAllAssociative('SELECT * FROM package_tasks ORDER BY id');
        try {
            $this->createTask($entry, new CreatePackageTaskDTO((string) $package->getId(), 'Wrong project', 'access-other', self::VALID_DOD, null, 'medium'), $other);
            self::fail('The work package must belong to the supplied project.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame(
                $entry === 'org handler' ? 'Werkpakket niet gevonden in dit project.' : 'Werkpakket hoort niet bij dit project.',
                $exception->getMessage(),
            );
        }
        self::assertSame($before, $em->getConnection()->fetchAllAssociative('SELECT * FROM package_tasks ORDER BY id'));
    }

    #[DataProvider('entryProvider')]
    public function testDefaultStatusMatchesExistingTaskFixturesDespiteOtherStatuses(string $entry): void
    {
        [$em, $project, $package] = $this->prepareActor('owner');
        $default = $em->getRepository(Status::class)->findOneBy(['scope' => 'PackageTask', 'name' => 'active']);
        self::assertInstanceOf(Status::class, $default);
        $this->addOtherStatuses($em);

        $this->createTask($entry, new CreatePackageTaskDTO((string) $package->getId(), 'Default status', 'access-default', self::VALID_DOD, null, 'medium'), $project);
        $em->clear();
        $task = $em->getRepository(PackageTask::class)->findOneBy(['slug' => 'access-default']);
        self::assertInstanceOf(PackageTask::class, $task);
        self::assertEquals($default->getId(), $task->getStatus()->getId());
        self::assertSame('PackageTask', $task->getStatus()->getScope());
        self::assertSame('active', $task->getStatus()->getName());
    }

    #[DataProvider('entryProvider')]
    public function testMissingDefaultStatusRejectsCreationWithoutWritingATask(string $entry): void
    {
        [$em, $project, $package] = $this->prepareActor('owner');
        $default = $em->getRepository(Status::class)->findOneBy(['scope' => 'PackageTask', 'name' => 'active']);
        self::assertInstanceOf(Status::class, $default);
        $default->setName('unavailable');
        $this->addOtherStatuses($em);
        $before = $em->getConnection()->fetchAllAssociative('SELECT * FROM package_tasks ORDER BY id');

        try {
            $this->createTask($entry, new CreatePackageTaskDTO((string) $package->getId(), 'Missing default', 'access-missing-default', self::VALID_DOD, null, 'medium'), $project);
            self::fail('Another scope or status must not replace the existing task default.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('Default task status not found.', $exception->getMessage());
        }
        $em->flush();
        self::assertSame($before, $em->getConnection()->fetchAllAssociative('SELECT * FROM package_tasks ORDER BY id'));
    }

    #[DataProvider('deletedParentProvider')]
    public function testDeletedParentsCannotBeUsedForTaskCreation(string $entry, string $parent): void
    {
        [$em, $project, $package] = $this->prepareActor('owner');
        ($parent === 'project' ? $project : $package)->softDelete();
        $em->flush();
        $before = $em->getConnection()->fetchAllAssociative('SELECT * FROM package_tasks ORDER BY id');

        try {
            $this->createTask($entry, new CreatePackageTaskDTO((string) $package->getId(), 'Deleted parent', 'access-deleted', self::VALID_DOD, null, 'medium'), $project);
            self::fail('Deleted projects and work packages must not accept new tasks.');
        } catch (\InvalidArgumentException) {
            self::assertSame($before, $em->getConnection()->fetchAllAssociative('SELECT * FROM package_tasks ORDER BY id'));
        }
    }

    public static function deniedCallerProvider(): iterable
    {
        foreach (self::entryProvider() as [$entry]) {
            foreach (['anonymous', 'outsider', 'no project rights', 'no organisation rights', 'platform administrator only', 'organisation manager only', 'foreign organisation rights'] as $actor) {
                yield $entry . ' / ' . $actor => [$entry, $actor];
            }
        }
    }

    #[DataProvider('invalidDoDProvider')]
    public function testAllCreationPathsRejectMissingOrBlankDoDWithoutWriting(string $entry, ?string $definitionOfDone): void
    {
        [$em, $project, $package] = $this->prepareActor('owner');
        $beforeTasks = $em->getConnection()->fetchAllAssociative('SELECT * FROM package_tasks ORDER BY id');
        $beforePackages = $em->getConnection()->fetchAllAssociative('SELECT * FROM work_packages ORDER BY id');

        try {
            $this->createTask($entry, new CreatePackageTaskDTO((string) $package->getId(), 'Missing DoD', 'access-missing-dod', $definitionOfDone, null, 'medium'), $project);
            self::fail('A task must have a concrete DoD before it can be saved.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('Vul een concrete taak-DoD met acceptatiecriteria in.', $exception->getMessage());
        }
        $em->flush();
        self::assertSame($beforeTasks, $em->getConnection()->fetchAllAssociative('SELECT * FROM package_tasks ORDER BY id'));
        self::assertSame($beforePackages, $em->getConnection()->fetchAllAssociative('SELECT * FROM work_packages ORDER BY id'));
    }

    public static function invalidDoDProvider(): iterable
    {
        foreach (self::entryProvider() as [$entry]) {
            foreach (['missing' => null, 'empty' => '', 'whitespace' => " \t\r\n ", 'unicode whitespace' => "\u{00A0}\u{2003}"] as $case => $value) {
                yield $entry . ' / ' . $case => [$entry, $value];
            }
        }
    }

    public static function allowedCallerProvider(): iterable
    {
        foreach (self::entryProvider() as [$entry]) {
            foreach (['owner', 'member'] as $actor) {
                yield $entry . ' / ' . $actor => [$entry, $actor];
            }
        }
    }

    #[DataProvider('invalidFieldsProvider')]
    public function testAllCreationPathsValidateTaskFieldsBeforeWriting(string $entry, string $title, string $slug, string $priority, string $message): void
    {
        [$em, $project, $package] = $this->prepareActor('owner');
        $before = $em->getConnection()->fetchAllAssociative('SELECT * FROM package_tasks ORDER BY id');
        try {
            $this->createTask($entry, new CreatePackageTaskDTO((string) $package->getId(), $title, $slug, self::VALID_DOD, null, $priority), $project);
            self::fail('Invalid task input must be rejected before persistence.');
        } catch (\InvalidArgumentException $exception) {
            self::assertStringContainsString($message, $exception->getMessage());
        }
        $em->flush();
        self::assertSame($before, $em->getConnection()->fetchAllAssociative('SELECT * FROM package_tasks ORDER BY id'));
    }

    public static function invalidFieldsProvider(): iterable
    {
        foreach (self::entryProvider() as [$entry]) {
            foreach ([
                'missing title' => ['', 'valid-slug', 'medium', 'taaktitel'],
                'blank title' => ["\u{00A0}\u{2003}", 'valid-slug', 'medium', 'taaktitel'],
                'long title' => [str_repeat('é', 256), 'valid-slug', 'medium', 'taaktitel'],
                'missing slug' => ['Task', '', 'medium', 'taakslug'],
                'invalid slug' => ['Task', 'Invalid Slug', 'medium', 'taakslug'],
                'long slug' => ['Task', str_repeat('a', 256), 'medium', 'taakslug'],
                'normal priority' => ['Task', 'valid-slug', 'normal', 'taakprioriteit'],
                'unknown priority' => ['Task', 'valid-slug', 'urgent', 'taakprioriteit'],
            ] as $case => $input) {
                yield $entry . ' / ' . $case => [$entry, ...$input];
            }
        }
    }

    #[DataProvider('priorityProvider')]
    public function testValidPrioritiesAndNormalisedInputArePersisted(string $entry, string $priority): void
    {
        [$em, $project, $package] = $this->prepareActor('owner');
        $this->createTask($entry, new CreatePackageTaskDTO(
            (string) $package->getId(), '  Scoped task  ', '  validation-valid  ', self::VALID_DOD,
            new \DateTimeImmutable('2028-02-29'), $priority,
        ), $project);
        $em->clear();
        $task = $em->getRepository(PackageTask::class)->findOneBy(['slug' => 'validation-valid']);
        self::assertInstanceOf(PackageTask::class, $task);
        self::assertSame('Scoped task', $task->getTitle());
        self::assertSame($priority, $task->getPriority());
        self::assertSame('2028-02-29', $task->getDueDate()->format('Y-m-d'));
    }

    public static function priorityProvider(): iterable
    {
        foreach (self::entryProvider() as [$entry]) {
            foreach (['low', 'medium', 'high'] as $priority) {
                yield $entry . ' / ' . $priority => [$entry, $priority];
            }
        }
    }

    #[DataProvider('duplicateSlugProvider')]
    public function testDuplicateSlugsIncludingArchivedTasksAreRejected(string $entry, bool $archived): void
    {
        [$em, $project, $package] = $this->prepareActor('owner');
        $task = static::getContainer()->get(PackageTaskCrudService::class)->create(new CreatePackageTaskDTO(
            (string) $package->getId(), 'Existing task', 'validation-duplicate', self::VALID_DOD, null, 'medium',
        ), $project);
        if ($archived) {
            $task->softDelete();
            $em->flush();
        }
        $before = $em->getConnection()->fetchAllAssociative('SELECT * FROM package_tasks ORDER BY id');
        try {
            $this->createTask($entry, new CreatePackageTaskDTO(
                (string) $package->getId(), 'Duplicate', ' validation-duplicate ', self::VALID_DOD, null, 'medium',
            ), $project);
            self::fail('A slug already used by a linked task must remain reserved.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('Deze slug wordt al gebruikt binnen dit werkpakket.', $exception->getMessage());
        }
        $em->flush();
        self::assertSame($before, $em->getConnection()->fetchAllAssociative('SELECT * FROM package_tasks ORDER BY id'));
    }

    public static function duplicateSlugProvider(): iterable
    {
        foreach (self::entryProvider() as [$entry]) {
            yield $entry . ' / active task' => [$entry, false];
            yield $entry . ' / archived task' => [$entry, true];
        }
    }

    #[DataProvider('entryProvider')]
    public function testSameSlugIsAllowedInAnotherWorkPackage(string $entry): void
    {
        [$em, $project, $package] = $this->prepareActor('owner');
        $this->createTask($entry, new CreatePackageTaskDTO(
            (string) $package->getId(), 'First task', 'validation-scoped', self::VALID_DOD, null, 'medium',
        ), $project);
        $other = (new WorkPackage())->setProject($project)->setStatus($package->getStatus())
            ->setTitle('Another package')->setSlug('validation-another');
        $em->persist($other);
        $em->flush();
        $this->createTask($entry, new CreatePackageTaskDTO(
            (string) $other->getId(), 'Second task', 'validation-scoped', self::VALID_DOD, null, 'medium',
        ), $project);
        self::assertSame(2, $em->getRepository(PackageTask::class)->count(['slug' => 'validation-scoped']));
    }

    public static function entryProvider(): iterable
    {
        foreach (['shared service', 'admin handler', 'admin service', 'org handler'] as $entry) {
            yield $entry => [$entry];
        }
    }

    public static function deletedParentProvider(): iterable
    {
        foreach (self::entryProvider() as [$entry]) {
            foreach (['project', 'work package'] as $parent) {
                yield $entry . ' / ' . $parent => [$entry, $parent];
            }
        }
    }

    private function addOtherStatuses(EntityManagerInterface $em): void
    {
        foreach ([['package_task', 'Open'], ['package_task', 'active'], ['PackageTask', 'Open'], ['packagetask', 'active']] as [$scope, $name]) {
            $em->persist((new Status())->setScope($scope)->setName($name)->setColourHex('#10B981'));
        }
        $em->flush();
    }

    private function createTask(string $entry, CreatePackageTaskDTO $dto, ?Project $project = null): void
    {
        $container = static::getContainer();
        $service = $container->get(PackageTaskCrudService::class);
        match ($entry) {
            'shared service' => $service->create($dto, $project),
            'admin handler' => (new CreatePackageTaskHandler($service))->handle($dto, $project),
            'admin service' => (new AdminPackageTaskCrudService(
                $container->get(EntityManagerInterface::class),
                $container->get(StatusRepository::class),
                $container->get(AuthorizationCheckerInterface::class),
                $container->get(PackageTaskInputValidator::class),
                $container->get(ProjectWriteLock::class),
            ))->create($dto, $project),
            'org handler' => $container->get(ManageProjectWorkPackagesHandler::class)->handle(
                $project ?? $container->get(EntityManagerInterface::class)->find(WorkPackage::class, $dto->workPackageId)->getProject(),
                new InputBag([
                    '_action' => 'create_package_task', 'workPackageId' => $dto->workPackageId,
                    'taskTitle' => $dto->title, 'taskSlug' => $dto->slug,
                    'taskPriority' => $dto->priority,
                    'taskDueDate' => $dto->dueDate?->format('Y-m-d') ?? '',
                ] + ($dto->description === null ? [] : ['taskDescription' => $dto->description])),
            ),
        };
    }

    /** @return array{EntityManagerInterface, Project, WorkPackage} */
    private function prepareActor(string $actor): array
    {
        static::bootKernel(['debug' => false]);
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $account = $em->getRepository(Account::class)->findOneBy(['username' => 'TestUser']);
        $outsider = $em->getRepository(Account::class)->findOneBy(['username' => 'AdminUser']);
        $project = $em->getRepository(Project::class)->findOneBy(['slug' => 'Test-Project']);
        $role = (new OrgRole())->setName('Task access editor')->setOrganisation($project->getOwnerOrganisation())
            ->setPermissionsMask(in_array($actor, ['no organisation rights', 'platform administrator only', 'foreign organisation rights'], true) ? 0 : Permission::ORG_MANAGE_PROJECTS->value);
        $em->persist($role);
        $em->persist((new OrgMember())->setAccount($account)->setOrganisation($project->getOwnerOrganisation())->setOrgRole($role));
        if (in_array($actor, ['member', 'no project rights', 'platform administrator only', 'organisation manager only'], true)) {
            $project->setOwnerAccount($outsider);
        }
        if ($actor === 'platform administrator only') {
            $adminRole = (new AdminRole())->setName('Scoped task platform admin')
                ->setPermissionsMask(Permission::ADMIN_MANAGE_ACCOUNTS->value | Permission::ADMIN_MANAGE_ORGANISATIONS->value);
            $admin = (new Admin())->setAccount($account)->setAdminRole($adminRole)
                ->setStatus($em->getRepository(Status::class)->findOneBy(['scope' => 'admin', 'name' => 'active']));
            $account->setAdmin($admin);
            $em->persist($adminRole);
            $em->persist($admin);
        }
        if ($actor === 'foreign organisation rights') {
            $otherOrganisation = (new Organisation())->setName('Other task organisation')->setSlug('scope-other-task-org')
                ->setStatus($project->getOwnerOrganisation()->getStatus());
            $otherRole = (new OrgRole())->setName('Foreign task project manager')->setOrganisation($otherOrganisation)
                ->setPermissionsMask(Permission::ORG_MANAGE_PROJECTS->value);
            $em->persist($otherOrganisation);
            $em->persist($otherRole);
            $em->persist((new OrgMember())->setAccount($account)->setOrganisation($otherOrganisation)->setOrgRole($otherRole));
        }
        if ($actor === 'member') {
            $participant = $em->getRepository(ProjectParticipant::class)->findOneBy(['project' => $project, 'profile' => $account->getProfile()]);
            $participant->join();
            $participant->getRole()->setPermissionsMask(ProjectPermission::VIEW->value | ProjectPermission::EDIT->value);
        }
        $em->flush();
        $user = $actor === 'outsider' ? $outsider : $account;
        $container->get('security.token_storage')->setToken(
            $actor === 'anonymous' ? null : new UsernamePasswordToken($user, 'main', $user->getRoles()),
        );
        return [$em, $project, $em->getRepository(WorkPackage::class)->findOneBy(['project' => $project])];
    }
}
