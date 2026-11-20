<?php

namespace App\Tests\Module\Org\Controller;

use App\Entity\Account\Account;
use App\Entity\Common\Status;
use App\Entity\Org\OrgMember;
use App\Entity\Org\OrgRole;
use App\Entity\Project\PackageTask;
use App\Entity\Project\Project;
use App\Entity\Project\ProjectParticipant;
use App\Entity\Project\WorkPackage;
use App\Kernel;
use App\Module\Admin\DTO\CreatePackageTaskDTO;
use App\Module\Main\Service\PackageTaskCrudService;
use App\Module\Org\Handler\ModifyTasksHandler;
use App\Security\Permission;
use App\Security\ProjectPermission;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class WorkPackageDeletionTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testEmptyPackageCanBeDeletedThroughTheExistingForm(): void
    {
        [$client, $em, $package] = $this->loginEditor();
        $id = $package->getId();
        $path = $this->path($package);
        $client->request('GET', $path);
        self::assertResponseIsSuccessful();
        $client->submitForm('Verwijderen');
        self::assertResponseRedirects($path, 303);
        $client->followRedirect();
        self::assertSelectorNotExists('.work-package-card');
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertNotNull($em->find(WorkPackage::class, $id)->getDeletedAt());
        $client->request('GET', '/projects/Test-Project/work-packages');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.work-package-card');
    }

    #[DataProvider('taskStateProvider')]
    public function testPackageWithTasksIsRejectedWithoutChangingStoredData(string $state): void
    {
        [$client, $em, $package] = $this->loginEditor();
        $status = (new Status())->setName($state)->setScope('PackageTask')->setColourHex('#10B981');
        $em->persist($status);
        $task = (new PackageTask())->setWorkPackage($package)->setStatus($status)
            ->setTitle('Preserved task')->setDescription('Preserved task data')
            ->setSlug('or11b-preserved')->setPriority('high')
            ->setAssignedProfile($package->getProject()->getOwnerAccount()->getProfile());
        if ($state === 'archived') {
            $task->softDelete();
        }
        $em->persist($task);
        $em->flush();
        $packageId = $package->getId();
        $taskId = $task->getId();
        $connection = $em->getConnection();
        $beforeTask = $connection->fetchAssociative('SELECT * FROM package_tasks WHERE id = ?', [$taskId], ['ulid']);
        $beforePackage = $connection->fetchAssociative('SELECT * FROM work_packages WHERE id = ?', [$packageId], ['ulid']);
        $path = $this->path($package);
        $client->request('GET', $path);
        $client->submitForm('Verwijderen');
        self::assertResponseRedirects($path, 303);
        $client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'Een werkpakket met taken kan niet worden verwijderd.');
        $connection = static::getContainer()->get(EntityManagerInterface::class)->getConnection();
        self::assertSame($beforeTask, $connection->fetchAssociative('SELECT * FROM package_tasks WHERE id = ?', [$taskId], ['ulid']));
        self::assertSame($beforePackage, $connection->fetchAssociative('SELECT * FROM work_packages WHERE id = ?', [$packageId], ['ulid']));
    }

    public static function taskStateProvider(): iterable
    {
        yield 'active task' => ['active'];
        yield 'completed task' => ['completed'];
        yield 'archived task' => ['archived'];
    }

    public function testCsrfAndDeletePermissionAreRequired(): void
    {
        [$client, $em, $package] = $this->loginEditor();
        $path = $this->path($package);
        $id = (string) $package->getId();
        $client->request('POST', $path, ['_action' => 'delete_work_package', 'workPackageId' => $id]);
        self::assertResponseStatusCodeSame(403);

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $package = $em->find(WorkPackage::class, $package->getId());
        $project = $package->getProject();
        $account = $project->getOwnerAccount();
        $project->setOwnerAccount($em->getRepository(Account::class)->findOneBy(['username' => 'AdminUser']));
        $participant = $em->getRepository(ProjectParticipant::class)->findOneBy(['project' => $project, 'profile' => $account->getProfile()]);
        $participant->getRole()->setPermissionsMask(ProjectPermission::VIEW->value | ProjectPermission::EDIT->value);
        $em->flush();
        $client->request('GET', $path);
        self::assertResponseIsSuccessful();
        $client->submitForm('Verwijderen');
        self::assertResponseStatusCodeSame(403);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertNull($em->find(WorkPackage::class, $package->getId())->getDeletedAt());
    }

    public function testAnotherProjectsPackageCannotBeDeleted(): void
    {
        [$client, $em, $package] = $this->loginEditor();
        $project = $package->getProject();
        $other = (new Project())->setOwnerAccount($project->getOwnerAccount())
            ->setOwnerOrganisation($project->getOwnerOrganisation())->setStatus($project->getStatus())
            ->setTitle('OR11B other')->setSlug('or11b-other')->setDescription('Other')
            ->setSummary('Other')->setVisibility('private')->setCapacity(5)->setStartDate(new \DateTimeImmutable());
        $foreign = (new WorkPackage())->setProject($other)->setStatus($package->getStatus())
            ->setTitle('OR11B foreign package')->setSlug('or11b-foreign');
        $em->persist($other);
        $em->persist($foreign);
        $em->flush();
        $path = $this->path($package);
        $page = $client->request('GET', $path);
        $form = $page->selectButton('Verwijderen')->form();
        $client->submit($form, ['workPackageId' => (string) $foreign->getId()]);
        self::assertResponseRedirects($path, 303);
        $client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'Werkpakket niet gevonden in dit project.');
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertNull($em->find(WorkPackage::class, $foreign->getId())->getDeletedAt());
    }

    public function testTaskCreationRefusesADeletedPackage(): void
    {
        [, $em, $package] = $this->loginEditor();
        $package->softDelete();
        $em->flush();
        $service = static::getContainer()->get(PackageTaskCrudService::class);
        $this->expectException(\InvalidArgumentException::class);
        $service->create(new CreatePackageTaskDTO((string) $package->getId(), 'Rejected', 'or11b-rejected', '', null, 'normal'));
    }

    public function testOR09CannotMoveATaskToADeletedPackage(): void
    {
        [, $em, $package] = $this->loginEditor();
        $task = static::getContainer()->get(PackageTaskCrudService::class)
            ->create(new CreatePackageTaskDTO((string) $package->getId(), 'Preserved', 'or11b-source', '', null, 'normal'));
        $deleted = (new WorkPackage())->setProject($package->getProject())->setStatus($package->getStatus())
            ->setTitle('Deleted target')->setSlug('or11b-deleted-target');
        $deleted->softDelete();
        $em->persist($deleted);
        $em->flush();
        $this->expectException(\InvalidArgumentException::class);
        try {
            static::getContainer()->get(ModifyTasksHandler::class)->handleModifyTask([
                'id' => (string) $task->getId(), 'workPackage' => (string) $deleted->getId(),
                'status' => (string) $task->getStatus()->getId(),
            ], $package->getProject());
        } finally {
            self::assertSame($package, $task->getWorkPackage());
        }
    }

    /** @return array{KernelBrowser, EntityManagerInterface, WorkPackage} */
    private function loginEditor(): array
    {
        $client = static::createClient(['debug' => false]);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $account = $em->getRepository(Account::class)->findOneBy(['username' => 'TestUser']);
        $project = $em->getRepository(Project::class)->findOneBy(['slug' => 'Test-Project']);
        $role = (new OrgRole())->setName('OR11B test editor')->setOrganisation($project->getOwnerOrganisation())
            ->setPermissionsMask(Permission::ORG_MANAGE_PROJECTS->value);
        $em->persist($role);
        $em->persist((new OrgMember())->setAccount($account)->setOrganisation($project->getOwnerOrganisation())->setOrgRole($role));
        $participant = $em->getRepository(ProjectParticipant::class)->findOneBy(['project' => $project, 'profile' => $account->getProfile()]);
        $participant->join();
        $participant->getRole()->setPermissionsMask(ProjectPermission::VIEW->value | ProjectPermission::EDIT->value | ProjectPermission::DELETE->value);
        $em->flush();
        $package = $em->getRepository(WorkPackage::class)->findOneBy(['project' => $project]);
        $client->loginUser($account);
        return [$client, $em, $package];
    }

    private function path(WorkPackage $package): string
    {
        return '/org/projects/modify/' . $package->getProject()->getId() . '/work-packages';
    }
}
