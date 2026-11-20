<?php

namespace App\Tests\Module\Main\Controller;

use App\Entity\Account\Account;
use App\Entity\Common\Status;
use App\Entity\Project\PackageTask;
use App\Entity\Project\Project;
use App\Entity\Project\ProjectParticipant;
use App\Entity\Project\WorkPackage;
use App\Kernel;
use App\Security\ProjectPermission;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class WorkPackageAccessTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testOnlyActiveMembersCanReadCurrentProjectPackages(): void
    {
        $client = static::createClient(['debug' => false]);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $account = $em->getRepository(Account::class)->findOneBy(['username' => 'TestUser']);
        $participant = $em->getRepository(ProjectParticipant::class)->findOneBy(['profile' => $account->getProfile()]);
        $project = $participant->getProject();
        $role = $participant->getRole();
        $role->setPermissionsMask(ProjectPermission::VIEW->value);
        $participant->join();
        $package = $em->getRepository(WorkPackage::class)->findOneBy(['project' => $project]);
        $package->setTitle('Visible MS12 package');
        foreach ($package->getWorkPackageTasks() as $task) {
            $task->softDelete();
        }
        $done = (new Status())->setName('completed')->setScope('PackageTask')->setColourHex('#10B981');
        $em->persist($done);
        $completed = (new PackageTask())->setWorkPackage($package)->setStatus($done)
            ->setTitle('Visible completed task')->setSlug('ms12-completed')->setPriority('normal');
        $open = (new PackageTask())->setWorkPackage($package)->setStatus($package->getStatus())
            ->setTitle('Visible open task')->setSlug('ms12-open')->setPriority('normal');
        $em->persist($completed);
        $em->persist($open);
        $deletedTask = (new PackageTask())->setWorkPackage($package)->setStatus($done)
            ->setTitle('Deleted MS12 task')->setSlug('ms12-deleted-task')->setPriority('normal');
        $deletedTask->softDelete();
        $em->persist($deletedTask);
        $hidden = (new WorkPackage())->setProject($project)->setStatus($package->getStatus())
            ->setTitle('Deleted MS12 package')->setSlug('ms12-deleted');
        $hidden->softDelete();
        $em->persist($hidden);
        $other = (new Project())->setOwnerAccount($project->getOwnerAccount())
            ->setOwnerOrganisation($project->getOwnerOrganisation())->setStatus($project->getStatus())
            ->setSlug('ms12-other')->setTitle('Other project')->setVisibility('public')
            ->setSummary('Other project')->setDescription('Other project')
            ->setStartDate(new \DateTimeImmutable())->setCapacity(10);
        $other->publish();
        $em->persist($other);
        $otherPackage = (new WorkPackage())->setProject($other)->setStatus($package->getStatus())
            ->setTitle('Other project secret')->setSlug('ms12-other-package');
        $em->persist($otherPackage);
        $em->flush();
        $participantId = $participant->getId();
        $roleId = $role->getId();
        $em->clear();
        $client->loginUser($account);
        $path = '/projects/' . $project->getSlug() . '/work-packages';

        $client->request('GET', $path);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.project-work-packages', 'Visible MS12 package');
        self::assertSelectorTextContains('.project-work-packages', 'Voortgang: 50% (1/2 taken gereed)');
        self::assertSelectorTextNotContains('.project-work-packages', 'Other project secret');
        self::assertSelectorTextNotContains('.project-work-packages', 'Deleted MS12 package');
        self::assertSelectorTextNotContains('.project-work-packages', 'Deleted MS12 task');
        self::assertCount(2, $client->getCrawler()->filter('.package-task-card'));
        self::assertSelectorExists('form[action*="/enroll"]');

        // Even the owner cannot open another project's packages without membership.
        $client->request('GET', '/projects/ms12-other/work-packages');
        self::assertResponseStatusCodeSame(403);
        self::assertStringNotContainsString('Other project secret', $client->getResponse()->getContent());

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->find(ProjectParticipant::class, $participantId)->leave();
        $em->flush();
        $client->request('GET', $path);
        self::assertResponseStatusCodeSame(403);
        self::assertStringNotContainsString('Visible MS12 package', $client->getResponse()->getContent());

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->find(ProjectParticipant::class, $participantId)->join();
        $em->find(\App\Entity\Project\ProjectRole::class, $roleId)->setPermissionsMask(0);
        $em->flush();
        $client->request('GET', $path);
        self::assertResponseStatusCodeSame(403);

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $outsider = $em->getRepository(Account::class)->findOneBy(['username' => 'AdminUser']);
        $client->loginUser($outsider);
        $client->request('GET', $path);
        self::assertResponseStatusCodeSame(403);

        $client->request('GET', '/projects/ms12-missing/work-packages');
        self::assertResponseStatusCodeSame(404);
    }
}
