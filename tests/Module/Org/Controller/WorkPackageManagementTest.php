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
        $client->request('POST', $path, [
            '_action' => 'create_work_package', '_token' => $token,
            'title' => 'Invalid date', 'slug' => 'or11-invalid', 'dueDate' => '2026-02-30',
        ]);
        self::assertResponseRedirects($path, 303);
        $client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'geldige deadline');
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
        $client->submit($edit, ['title' => 'OR11 Updated', 'description' => 'Changed description', 'dueDate' => '']);
        self::assertResponseRedirects($path, 303);
        $client->followRedirect();
        self::assertSelectorTextContains('.work-package-card-grid', 'OR11 Updated');
        $taskForm = $client->getCrawler()->filter('form')->reduce(
            static fn ($form): bool => $form->filter('input[name="_action"][value="create_package_task"]')->count() > 0
                && $form->filter('input[name="workPackageId"][value="' . $packageId . '"]')->count() > 0,
        )->form();
        $client->submit($taskForm, ['taskTitle' => 'OR11 Child', 'taskSlug' => 'or11-child', 'taskDueDate' => '2026-12-15']);
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
        self::assertContains($task->getStatus()->getScope(), ['PackageTask', 'package_task']);

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
