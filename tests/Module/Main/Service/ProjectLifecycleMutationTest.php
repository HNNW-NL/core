<?php

namespace App\Tests\Module\Main\Service;

use App\Entity\Account\Account;
use App\Entity\Common\Status;
use App\Entity\Org\OrgMember;
use App\Entity\Org\OrgRole;
use App\Entity\Project\Project;
use App\Entity\Project\WorkPackage;
use App\Kernel;
use App\Module\Admin\DTO\CreatePackageTaskDTO;
use App\Module\Admin\DTO\CreateWorkPackageDTO;
use App\Module\Admin\DTO\UpdateWorkPackageDTO;
use App\Module\Admin\Handler\CreatePackageTaskHandler;
use App\Module\Admin\Handler\CreateWorkPackageHandler;
use App\Module\Admin\Handler\DeleteWorkPackageHandler;
use App\Module\Admin\Handler\UpdateWorkPackageHandler;
use App\Module\Admin\Service\PackageTaskCrudService as AdminPackageTaskCrudService;
use App\Module\Admin\Service\WorkPackageCrudService as AdminWorkPackageCrudService;
use App\Module\Main\DTO\ChangeWorkPackageStatusDTO;
use App\Module\Main\Handler\ChangeWorkPackageStatusHandler;
use App\Module\Main\Service\PackageTaskCrudService;
use App\Module\Main\Service\PackageTaskInputValidator;
use App\Module\Main\Service\WorkPackageCrudService;
use App\Module\Org\Handler\CreateWorkPackageHandler as OrgCreateWorkPackageHandler;
use App\Module\Org\Handler\ManageProjectWorkPackagesHandler;
use App\Module\Org\Service\ProjectWriteLock;
use App\Repository\Common\StatusRepository;
use App\Security\Permission;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class ProjectLifecycleMutationTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    #[DataProvider('blockedStateProvider')]
    public function testEveryMutationEntryRejectsNonWritableProjectContexts(string $entry, string $action, string $state): void
    {
        [$em, $project, $package] = $this->prepareEditor();
        if ($state === 'deleted organisation') {
            $project->getOwnerOrganisation()->softDelete();
        } else {
            $status = $this->projectStatus($em, $state === 'wrong scope' ? 'organisation' : 'project', $state === 'wrong scope' ? 'active' : $state);
            $project->setStatus($status);
        }
        $em->flush();
        $before = $this->snapshot($em);
        try {
            $this->mutate($entry, $action, $project, $package);
            self::fail('A closed or invalid project context must not accept mutations.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('The current project state does not allow this change.', $exception->getMessage());
        }
        $em->flush();
        self::assertSame($before, $this->snapshot($em));
    }

    public static function blockedStateProvider(): iterable
    {
        foreach (self::entryProvider() as [$entry, $action]) {
            foreach (['completed', 'archived', 'withdrawn', 'unsupported', 'deleted organisation', 'wrong scope'] as $state) {
                yield "$entry / $action / $state" => [$entry, $action, $state];
            }
        }
    }

    #[DataProvider('allowedStateProvider')]
    public function testWritableLifecycleStatesPreserveExistingMutations(string $action, string $state): void
    {
        [$em, $project, $package] = $this->prepareEditor();
        $project->setStatus($this->projectStatus($em, 'project', $state));
        $em->flush();
        $this->mutate('shared', $action, $project, $package);
        $em->clear();
        $connection = $em->getConnection();
        match ($action) {
            'create package' => self::assertSame(1, $em->getRepository(WorkPackage::class)->count(['slug' => 'lifecycle-created'])),
            'update package' => self::assertSame('lifecycle-updated', $em->find(WorkPackage::class, $package->getId())->getSlug()),
            'delete package' => self::assertNotNull($em->find(WorkPackage::class, $package->getId())->getDeletedAt()),
            'create task' => self::assertSame(1, (int) $connection->fetchOne("SELECT COUNT(*) FROM package_tasks WHERE slug = 'lifecycle-task'")),
            'change package status' => self::assertSame('Assigned', $em->find(WorkPackage::class, $package->getId())->getStatus()->getName()),
        };
    }

    public static function allowedStateProvider(): iterable
    {
        foreach (self::coreActionProvider() as [$action]) {
            foreach (['active', 'draft', 'published', 'applications_closed'] as $state) {
                yield "$action / $state" => [$action, $state];
            }
        }
    }

    #[DataProvider('coreActionProvider')]
    public function testLifecycleIsRecheckedFromTheLockedProjectRatherThanStaleObjects(string $action): void
    {
        [$em, $project, $package] = $this->prepareEditor();
        $completed = $this->projectStatus($em, 'project', 'completed');
        $em->flush();
        $em->getConnection()->executeStatement('UPDATE projects SET status_id = ? WHERE id = ?', [$completed->getId(), $project->getId()], ['ulid', 'ulid']);
        self::assertSame('active', $project->getStatus()->getName());
        $before = $this->snapshot($em);
        try {
            $this->mutate('shared', $action, $project, $package);
            self::fail('The authoritative project lifecycle must be read under the project lock.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('The current project state does not allow this change.', $exception->getMessage());
        }
        $em->flush();
        self::assertSame($before, $this->snapshot($em));
    }

    public static function coreActionProvider(): iterable
    {
        foreach (['create package', 'update package', 'delete package', 'create task', 'change package status'] as $action) {
            yield $action => [$action];
        }
    }

    public function testTaskCreationUsesTheSameExclusiveProjectLockAsWorkPackageWrites(): void
    {
        [$em, $project, $package] = $this->prepareEditor();
        $this->mutate('shared', 'create task', $project, $package);
        $params = $em->getConnection()->getParams();
        unset($params['driverClass'], $params['wrapperClass']);
        $other = DriverManager::getConnection($params);
        $other->beginTransaction();
        try {
            $other->executeQuery('SELECT id FROM projects WHERE id = ? FOR SHARE NOWAIT', [$project->getId()->toRfc4122()]);
            self::fail('Task creation must exclude concurrent project lifecycle changes.');
        } catch (DriverException $exception) {
            self::assertSame('55P03', $exception->getSQLState());
        } finally {
            $other->rollBack();
            $other->close();
        }
    }

    public function testStatusTransitionUsesTheLockedWorkPackageRatherThanAStaleStatus(): void
    {
        [$em, $project, $package] = $this->prepareEditor();
        $closed = $this->projectStatus($em, 'work_package', 'Closed');
        $em->flush();
        $em->getConnection()->executeStatement('UPDATE work_packages SET status_id = ? WHERE id = ?', [$closed->getId(), $package->getId()], ['ulid', 'ulid']);
        self::assertSame('Open', $package->getStatus()->getName());
        $before = $this->snapshot($em);

        try {
            $this->mutate('shared', 'change package status', $project, $package);
            self::fail('A transition must use the current status from the locked work package.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('Cannot change work package status from "Closed" to "Assigned".', $exception->getMessage());
        }

        $em->flush();
        self::assertSame($before, $this->snapshot($em));
    }

    public static function entryProvider(): iterable
    {
        foreach (['shared', 'admin service', 'admin handler', 'org management'] as $entry) {
            foreach (['create package', 'update package', 'delete package', 'create task'] as $action) {
                yield "$entry / $action" => [$entry, $action];
            }
        }
        yield 'org create handler' => ['org create handler', 'create package'];
        yield 'work package status handler' => ['shared', 'change package status'];
    }

    private function mutate(string $entry, string $action, Project $project, WorkPackage $package): void
    {
        $container = static::getContainer();
        $packages = $container->get(WorkPackageCrudService::class);
        $tasks = $container->get(PackageTaskCrudService::class);
        $create = new CreateWorkPackageDTO((string) $project->getId(), 'Created', 'lifecycle-created', '', null);
        $update = new UpdateWorkPackageDTO((string) $package->getId(), 'Updated', 'lifecycle-updated', '', null);
        $task = new CreatePackageTaskDTO((string) $package->getId(), 'Task', 'lifecycle-task', 'Alle bronnen zijn gecontroleerd.', null, 'medium');
        if ($entry === 'admin service') {
            $packages = new AdminWorkPackageCrudService(
                $container->get(EntityManagerInterface::class), $container->get(StatusRepository::class),
                $container->get('security.authorization_checker'), $container->get(ProjectWriteLock::class),
            );
            $tasks = new AdminPackageTaskCrudService(
                $container->get(EntityManagerInterface::class), $container->get(StatusRepository::class),
                $container->get('security.authorization_checker'), $container->get(PackageTaskInputValidator::class),
                $container->get(ProjectWriteLock::class),
            );
        }
        if ($entry === 'admin handler') {
            match ($action) {
                'create package' => (new CreateWorkPackageHandler($packages))->handle($create),
                'update package' => (new UpdateWorkPackageHandler($packages))->handle($update),
                'delete package' => (new DeleteWorkPackageHandler($packages))->handle((string) $package->getId()),
                'create task' => (new CreatePackageTaskHandler($tasks))->handle($task),
            };
        } elseif ($entry === 'org management') {
            $container->get(ManageProjectWorkPackagesHandler::class)->handle($project, new InputBag([
                '_action' => match ($action) {'create package' => 'create_work_package', 'update package' => 'update_work_package', 'delete package' => 'delete_work_package', 'create task' => 'create_package_task'},
                'workPackageId' => (string) $package->getId(),
                'title' => $action === 'update package' ? $update->title : $create->title,
                'slug' => $action === 'update package' ? $update->slug : $create->slug,
                'taskTitle' => $task->title, 'taskSlug' => $task->slug, 'taskDescription' => $task->description,
            ]));
        } elseif ($entry === 'org create handler') {
            (new OrgCreateWorkPackageHandler($packages))->handle($create);
        } else {
            match ($action) {
                'create package' => $packages->create($create, $project),
                'update package' => $packages->update($update, $project),
                'delete package' => $packages->delete((string) $package->getId(), $project),
                'create task' => $tasks->create($task, $project),
                'change package status' => $container->get(ChangeWorkPackageStatusHandler::class)->handle(
                    new ChangeWorkPackageStatusDTO((string) $package->getId(), 'Assigned'), $project,
                ),
            };
        }
    }

    /** @return array{EntityManagerInterface, Project, WorkPackage} */
    private function prepareEditor(): array
    {
        static::bootKernel(['debug' => false]);
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $account = $em->getRepository(Account::class)->findOneBy(['username' => 'TestUser']);
        $project = $em->getRepository(Project::class)->findOneBy(['slug' => 'Test-Project']);
        $role = (new OrgRole())->setOrganisation($project->getOwnerOrganisation())->setName('Lifecycle editor')->setPermissionsMask(Permission::ORG_MANAGE_PROJECTS->value);
        $em->persist($role);
        $em->persist((new OrgMember())->setAccount($account)->setOrganisation($project->getOwnerOrganisation())->setOrgRole($role));
        $em->flush();
        $container->get('security.token_storage')->setToken(new UsernamePasswordToken($account, 'main', $account->getRoles()));

        return [$em, $project, $em->getRepository(WorkPackage::class)->findOneBy(['project' => $project])];
    }

    private function projectStatus(EntityManagerInterface $em, string $scope, string $name): Status
    {
        $status = $em->getRepository(Status::class)->findOneBy(['scope' => $scope, 'name' => $name]);
        if ($status === null) {
            $status = (new Status())->setScope($scope)->setName($name)->setColourHex('#10B981');
            $em->persist($status);
        }
        return $status;
    }

    private function snapshot(EntityManagerInterface $em): array
    {
        $db = $em->getConnection();
        return [
            $db->fetchAllAssociative('SELECT * FROM projects ORDER BY id'),
            $db->fetchAllAssociative('SELECT * FROM work_packages ORDER BY id'),
            $db->fetchAllAssociative('SELECT * FROM package_tasks ORDER BY id'),
        ];
    }
}
