<?php

namespace App\Tests\Module\Org\Controller;

use App\Entity\Account\Account;
use App\Entity\Org\OrgMember;
use App\Entity\Org\OrgRole;
use App\Entity\Project\PackageTask;
use App\Entity\Project\Project;
use App\Entity\Project\ProjectParticipant;
use App\Entity\Project\WorkPackage;
use App\Kernel;
use App\Security\Permission;
use App\Security\ProjectPermission;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class WorkPackageManagementTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testCreateEditAndAddTaskAreVisibleInMS12(): void
    {
        $client = static::createClient(['debug' => false]);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $account = $em->getRepository(Account::class)->findOneBy(['username' => 'TestUser']);
        $project = $em->getRepository(Project::class)->findOneBy(['slug' => 'Test-Project']);
        $role = (new OrgRole())->setName('OR11 test editor')->setOrganisation($project->getOwnerOrganisation())
            ->setPermissionsMask(Permission::ORG_MANAGE_PROJECTS->value);
        $member = (new OrgMember())->setAccount($account)->setOrganisation($project->getOwnerOrganisation())->setOrgRole($role);
        $em->persist($role);
        $em->persist($member);
        $participant = $em->getRepository(ProjectParticipant::class)->findOneBy(['profile' => $account->getProfile(), 'project' => $project]);
        $participant->join();
        $participant->getRole()->setPermissionsMask(ProjectPermission::VIEW->value | ProjectPermission::EDIT->value);
        $em->flush();
        $client->loginUser($account);
        $path = '/org/projects/modify/' . $project->getId() . '/work-packages';

        $page = $client->request('GET', $path);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="/org/projects/modify/' . $project->getId() . '/tasks"]');
        $before = (int) $page->filter('.work-packages-stat-card strong')->eq(1)->text();
        $packageCount = (int) $page->filter('.work-packages-stat-card strong')->eq(0)->text();
        $token = $page->filter('form input[name="_token"]')->last()->attr('value');
        $snapshot = $em->getConnection()->fetchAllAssociative('SELECT * FROM work_packages ORDER BY id');
        $client->request('POST', $path, [
            '_action' => 'create_work_package', '_token' => $token,
            'title' => 'Unavailable responsible', 'slug' => 'or11-unavailable-responsible',
            'responsibleProfileId' => (string) $account->getProfile()->getId(),
        ]);
        self::assertResponseRedirects($path, 303);
        $client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'databasewijziging door de Product Owner');
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertSame($snapshot, $em->getConnection()->fetchAllAssociative('SELECT * FROM work_packages ORDER BY id'));
        foreach (['2026-02-30', 'next monday', "2026-12-20\0"] as $invalidDate) {
            $client->request('POST', $path, [
                '_action' => 'create_work_package', '_token' => $token,
                'title' => 'Invalid date', 'slug' => 'or11-invalid', 'dueDate' => $invalidDate,
            ]);
            self::assertResponseRedirects($path, 303);
            $client->followRedirect();
            self::assertSelectorTextContains('[role="alert"]', 'geldige deadline');
            $em = static::getContainer()->get(EntityManagerInterface::class);
            self::assertSame($snapshot, $em->getConnection()->fetchAllAssociative('SELECT * FROM work_packages ORDER BY id'));
        }
        self::assertSame($packageCount, (int) $client->getCrawler()->filter('.work-packages-stat-card strong')->eq(0)->text());
        $client->submitForm('Werkpakket opslaan', [
            'title' => 'OR11 Acceptance', 'slug' => 'or11-acceptance',
            'description' => 'Initial description', 'dueDate' => '2026-12-20',
        ]);
        self::assertResponseRedirects($path, 303);
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.work-package-card-grid', 'OR11 Acceptance');

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $package = $em->getRepository(WorkPackage::class)->findOneBy(['slug' => 'or11-acceptance']);
        self::assertInstanceOf(WorkPackage::class, $package);
        self::assertEquals($project->getId(), $package->getProject()->getId());
        self::assertSame('2026-12-20', $package->getDueDate()->format('Y-m-d'));
        $packageId = (string) $package->getId();
        $edit = $client->getCrawler()->filter('form')->reduce(
            static fn ($form): bool => $form->filter('input[name="_action"][value="update_work_package"]')->count() > 0
                && $form->filter('input[name="workPackageId"][value="' . $packageId . '"]')->count() > 0,
        )->form();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $beforeEdit = $em->getConnection()->fetchAllAssociative('SELECT * FROM work_packages ORDER BY id');
        $client->request('POST', $path, [
            '_action' => 'update_work_package', '_token' => $edit->get('_token')->getValue(),
            'workPackageId' => $packageId, 'title' => 'Must not be saved', 'slug' => 'or11-acceptance',
            'description' => 'Must not be saved', 'dueDate' => "2026-12-20\0",
        ]);
        self::assertResponseRedirects($path, 303);
        $client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'geldige deadline');
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertSame($beforeEdit, $em->getConnection()->fetchAllAssociative('SELECT * FROM work_packages ORDER BY id'));
        $client->submit($edit, ['title' => 'OR11 Updated', 'description' => 'Changed description', 'dueDate' => '']);
        self::assertResponseRedirects($path, 303);
        $client->followRedirect();
        self::assertSelectorTextContains('.work-package-card-grid', 'OR11 Updated');
        $taskForm = $client->getCrawler()->filter('form')->reduce(
            static fn ($form): bool => $form->filter('input[name="_action"][value="create_package_task"]')->count() > 0
                && $form->filter('input[name="workPackageId"][value="' . $packageId . '"]')->count() > 0,
        )->form();
        self::assertSelectorExists('textarea[name="taskDescription"][required]');
        self::assertSelectorTextContains('label[for="taskDescription-' . $packageId . '"]', 'taak-DoD');
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $beforeTasks = $em->getConnection()->fetchAllAssociative('SELECT * FROM package_tasks ORDER BY id');
        $beforePackages = $em->getConnection()->fetchAllAssociative('SELECT * FROM work_packages ORDER BY id');
        foreach ([[], ['taskDescription' => ''], ['taskDescription' => " \t\r\n "], ['taskDescription' => "\u{00A0}\u{2003}"]] as $invalidDoD) {
            $client->request('POST', $path, [
                '_action' => 'create_package_task', '_token' => $taskForm->get('_token')->getValue(),
                'workPackageId' => $packageId, 'taskTitle' => 'Missing DoD', 'taskSlug' => 'or11-missing-dod',
            ] + $invalidDoD);
            self::assertResponseRedirects($path, 303);
            $client->followRedirect();
            self::assertSelectorTextContains('[role="alert"]', 'Vul een concrete taak-DoD met acceptatiecriteria in.');
            $em = static::getContainer()->get(EntityManagerInterface::class);
            self::assertSame($beforeTasks, $em->getConnection()->fetchAllAssociative('SELECT * FROM package_tasks ORDER BY id'));
            self::assertSame($beforePackages, $em->getConnection()->fetchAllAssociative('SELECT * FROM work_packages ORDER BY id'));
        }
        $definitionOfDone = "Alle drie bronnen zijn gecontroleerd.\nHet overzicht bevat bronverwijzingen.";
        foreach ([
            [['taskTitle' => ''], 'taaktitel'],
            [['taskSlug' => 'Invalid Slug'], 'taakslug'],
            [['taskPriority' => 'normal'], 'taakprioriteit'],
            [['taskPriority' => 'urgent'], 'taakprioriteit'],
            [['taskDueDate' => 'next monday'], 'geldige deadline'],
            [['taskDueDate' => '2026-02-30'], 'geldige deadline'],
            [['taskDueDate' => '2026-12-15T10:30'], 'geldige deadline'],
            [['taskDueDate' => "2026-12-15\0"], 'geldige deadline'],
        ] as [$invalidInput, $message]) {
            $client->request('POST', $path, array_replace([
                '_action' => 'create_package_task', '_token' => $taskForm->get('_token')->getValue(),
                'workPackageId' => $packageId, 'taskTitle' => 'Invalid input', 'taskSlug' => 'or11-invalid-task',
                'taskDescription' => $definitionOfDone, 'taskPriority' => 'medium',
            ], $invalidInput));
            self::assertResponseRedirects($path, 303);
            $client->followRedirect();
            self::assertSelectorTextContains('[role="alert"]', $message);
            $em = static::getContainer()->get(EntityManagerInterface::class);
            self::assertSame($beforeTasks, $em->getConnection()->fetchAllAssociative('SELECT * FROM package_tasks ORDER BY id'));
            self::assertSame($beforePackages, $em->getConnection()->fetchAllAssociative('SELECT * FROM work_packages ORDER BY id'));
        }
        $client->submit($taskForm, [
            'taskTitle' => 'OR11 Child', 'taskSlug' => 'or11-child', 'taskDueDate' => '2026-12-15',
            'taskDescription' => $definitionOfDone,
        ]);
        self::assertResponseRedirects($path, 303);
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSame($before + 1, (int) $client->getCrawler()->filter('.work-packages-stat-card strong')->eq(1)->text());
        self::assertSelectorTextContains('.work-package-card-grid', 'OR11 Child');
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $saved = $em->getRepository(WorkPackage::class)->findOneBy(['slug' => 'or11-acceptance']);
        self::assertNull($saved->getDueDate());
        self::assertSame('Changed description', $saved->getDescription());
        $task = $em->getRepository(PackageTask::class)->findOneBy(['slug' => 'or11-child']);
        self::assertSame($definitionOfDone, $task->getDescription());
        self::assertSame('medium', $task->getPriority());
        self::assertSame('2026-12-15', $task->getDueDate()->format('Y-m-d'));
        self::assertSame('PackageTask', $task->getStatus()->getScope());
        self::assertSame('active', $task->getStatus()->getName());

        $client->request('GET', '/projects/Test-Project/work-packages');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.project-work-packages', 'OR11 Updated');
        self::assertSelectorTextContains('.project-work-packages', 'OR11 Child');
        self::assertSelectorTextContains('.project-work-packages', 'Voortgang: 0% (0/1 taken gereed)');

        $page = $client->request('GET', $path);
        $token = $page->filter('form input[name="_token"]')->last()->attr('value');
        $client->request('POST', $path, [
            '_action' => 'create_work_package', '_token' => $token,
            'title' => 'Duplicate', 'slug' => 'or11-acceptance',
        ]);
        self::assertResponseRedirects($path, 303);
        $client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'Deze slug wordt al gebruikt');
        self::assertSame($packageCount + 1, (int) $client->getCrawler()->filter('.work-packages-stat-card strong')->eq(0)->text());

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $other = (new Project())->setOwnerAccount($em->find(Account::class, $account->getId()))
            ->setOwnerOrganisation($em->find(Project::class, $project->getId())->getOwnerOrganisation())
            ->setStatus($em->find(Project::class, $project->getId())->getStatus())
            ->setTitle('OR11 other project')->setSlug('or11-other')->setVisibility('private')
            ->setSummary('Other project')->setDescription('Other project')
            ->setStartDate(new \DateTimeImmutable())->setCapacity(5);
        $em->persist($other);
        $otherPackage = (new WorkPackage())->setProject($other)->setStatus($em->find(WorkPackage::class, $saved->getId())->getStatus())
            ->setTitle('OR11 foreign package')->setSlug('or11-foreign');
        $em->persist($otherPackage);
        $em->flush();
        $client->request('POST', $path, [
            '_action' => 'update_work_package', '_token' => $token, 'workPackageId' => (string) $otherPackage->getId(),
            'title' => 'Tampered', 'slug' => 'or11-tampered',
        ]);
        self::assertResponseRedirects($path, 303);
        $client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'Werkpakket niet gevonden in dit project.');
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertSame('OR11 foreign package', $em->find(WorkPackage::class, $otherPackage->getId())->getTitle());

        $client->request('POST', $path, ['_action' => 'create_work_package', 'title' => 'No token', 'slug' => 'or11-no-token']);
        self::assertResponseStatusCodeSame(403);
    }
}
