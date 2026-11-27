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
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
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

        self::assertCount(1, $client->getCrawler()->filter('nav[aria-label="Projectnavigatie"]'));
        self::assertSelectorExists('nav.project-navigation a[href="/projects/' . $project->getSlug() . '/updates"]');
        $client->clickLink('Overzicht');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main h2', $project->getTitle());
        self::assertSelectorExists('nav.project-navigation a[aria-current="page"][href="/projects/' . $project->getSlug() . '"]');
        $client->clickLink('Werkpakketten');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.project-work-packages', 'Visible MS12 package');
        $client->clickLink('Taken');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.project-tasks', 'Visible open task');
        $client->clickLink('Werkpakketten');
        self::assertResponseIsSuccessful();

        // Even the owner cannot open another project's packages without membership.
        $client->request('GET', '/projects/ms12-other');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main h2', 'Other project');
        self::assertSelectorExists('a[href="/projects/ms12-other/apply"]');
        $client->clickLink('Werkpakketten');
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

    #[DataProvider('deniedMembershipProvider')]
    public function testInvalidMembershipNeverRevealsProjectData(string $membership): void
    {
        [$client, $em, $participant, $package] = $this->loginActiveMember();
        switch ($membership) {
            case 'pending':
                $pending = (new ProjectParticipant())
                    ->setProject($participant->getProject())
                    ->setProfile($participant->getProfile())
                    ->setRole($participant->getRole())
                    ->setStatus($participant->getStatus());
                $em->remove($participant);
                $em->flush();
                $em->persist($pending);
                break;
            case 'left':
                $participant->leave();
                break;
            case 'no role':
                $participant->setRole(null);
                break;
            case 'no view permission':
                $participant->getRole()->setPermissionsMask(ProjectPermission::EDIT->value);
                break;
        }
        $em->flush();
        $client->request('GET', '/projects/' . $package->getProject()->getSlug() . '/work-packages');

        self::assertResponseStatusCodeSame(403);
        self::assertSelectorNotExists('.project-work-packages');
        self::assertStringNotContainsString('Visible MS12 package', $client->getResponse()->getContent());
    }

    public static function deniedMembershipProvider(): iterable
    {
        foreach (['pending', 'left', 'no role', 'no view permission'] as $membership) {
            yield $membership => [$membership];
        }
    }

    #[DataProvider('unknownSlugProvider')]
    public function testUnknownSlugReturns404(string $slug): void
    {
        [$client] = $this->loginActiveMember();
        $client->request('GET', '/projects/' . $slug . '/work-packages');

        self::assertResponseStatusCodeSame(404);
        self::assertSelectorNotExists('.project-work-packages');
    }

    public static function unknownSlugProvider(): iterable
    {
        yield 'unknown' => ['ms12-does-not-exist'];
        yield 'encoded invalid characters' => ['%3Cinvalid%3E'];
        yield 'missing slug' => [''];
    }

    public function testAnonymousVisitorIsRedirectedToLogin(): void
    {
        $client = static::createClient(['debug' => false]);
        $client->request('GET', '/projects/Test-Project/work-packages');

        self::assertResponseRedirects('/auth/login');
        self::assertSelectorNotExists('.project-work-packages');
    }

    public function testDeletedProjectReturns404(): void
    {
        [$client, $em, $participant] = $this->loginActiveMember();
        $project = $participant->getProject();
        $project->softDelete();
        $em->flush();
        $client->request('GET', '/projects/' . $project->getSlug() . '/work-packages');

        self::assertResponseStatusCodeSame(404);
        self::assertSelectorNotExists('.project-work-packages');
    }

    public function testProjectWithoutPackagesShowsEmptyState(): void
    {
        [$client, $em, $participant] = $this->loginActiveMember();
        $project = $participant->getProject();
        foreach ($em->getRepository(WorkPackage::class)->findBy(['project' => $project]) as $package) {
            $package->softDelete();
        }
        $em->flush();
        $client->request('GET', '/projects/' . $project->getSlug() . '/work-packages');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.project-work-packages', 'Er zijn nog geen werkpakketten of taken beschikbaar');
        self::assertSelectorNotExists('.work-package-card, form[action*="/enroll"]');
    }

    public function testNonOwnerMemberCanViewPackageWithoutTasks(): void
    {
        [$client, $em, $participant, $package] = $this->loginActiveMember();
        $project = $participant->getProject();
        $project->setOwnerAccount($em->getRepository(Account::class)->findOneBy(['username' => 'AdminUser']));
        foreach ($package->getWorkPackageTasks() as $task) {
            $task->softDelete();
        }
        $em->flush();
        $em->clear();
        $client->request('GET', '/projects/' . $project->getSlug() . '/work-packages');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.work-package-card', 'Voortgang: 0% (0/0 taken gereed)');
        self::assertSelectorTextContains('.work-package-card', 'Er zijn nog geen taken binnen dit werkpakket.');
        self::assertSelectorNotExists('form[action*="/enroll"]');
    }

    public function testTaskAssignmentAndCompletedProgressAreRenderedSafely(): void
    {
        [$client, $em, $participant, $package] = $this->loginActiveMember();
        $project = $participant->getProject();
        foreach ($package->getWorkPackageTasks() as $task) {
            $task->softDelete();
        }
        $done = (new Status())->setName('completed')->setScope('PackageTask')->setColourHex('#10B981');
        $em->persist($done);
        $otherAccount = $em->getRepository(Account::class)->findOneBy(['username' => 'AdminUser']);
        $ownTask = (new PackageTask())->setWorkPackage($package)->setStatus($done)
            ->setTitle('<script>ms12()</script>')->setSlug('ms12-own')->setPriority('normal')
            ->setAssignedProfile($participant->getProfile());
        $otherTask = (new PackageTask())->setWorkPackage($package)->setStatus($done)
            ->setTitle('Other assigned task')->setSlug('ms12-assigned')->setPriority('normal')
            ->setAssignedProfile($otherAccount->getProfile());
        $em->persist($ownTask);
        $em->persist($otherTask);
        $em->flush();
        $em->clear();
        $client->request('GET', '/projects/' . $project->getSlug() . '/work-packages');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.work-package-card', 'Voortgang: 100% (2/2 taken gereed)');
        self::assertSelectorTextContains('.package-task-card', 'Jij bent ingeschreven op deze taak.');
        self::assertStringContainsString('Deze taak is al toegewezen.', $client->getResponse()->getContent());
        self::assertSelectorNotExists('.package-task-card script, form[action*="/enroll"]');
        self::assertStringContainsString('&lt;script&gt;ms12()&lt;/script&gt;', $client->getResponse()->getContent());
    }

    /** @return array{KernelBrowser, EntityManagerInterface, ProjectParticipant, WorkPackage} */
    private function loginActiveMember(): array
    {
        $client = static::createClient(['debug' => false]);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $account = $em->getRepository(Account::class)->findOneBy(['username' => 'TestUser']);
        self::assertInstanceOf(Account::class, $account);
        $participant = $em->getRepository(ProjectParticipant::class)->findOneBy(['profile' => $account->getProfile()]);
        self::assertInstanceOf(ProjectParticipant::class, $participant);
        $participant->join();
        $participant->getRole()->setPermissionsMask(ProjectPermission::VIEW->value);
        $package = $em->getRepository(WorkPackage::class)->findOneBy(['project' => $participant->getProject()]);
        self::assertInstanceOf(WorkPackage::class, $package);
        $package->setTitle('Visible MS12 package');
        $em->flush();
        $client->loginUser($account);

        return [$client, $em, $participant, $package];
    }
}
