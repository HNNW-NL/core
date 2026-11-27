<?php

namespace App\Tests\Module\Main\Controller;

use App\Entity\Account\Account;
use App\Entity\Common\Status;
use App\Entity\Project\PackageTask;
use App\Entity\Project\Project;
use App\Entity\Project\ProjectParticipant;
use App\Entity\Project\WorkPackage;
use App\Kernel;
use App\Module\Main\DTO\WorkPackageTaskEnrollmentStatus;
use App\Module\Main\Service\WorkPackageTaskEnrollmentService;
use App\Repository\Project\PackageTaskRepository;
use App\Security\ProjectPermission;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class TaskEnrollmentTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    #[DataProvider('pageProvider')]
    public function testFormReachesEnrollmentControllerAndPersistsAssignment(string $page): void
    {
        [$client, $em, $participant, $task] = $this->loginMember();
        $slug = $participant->getProject()->getSlug();
        $taskId = $task->getId();
        $profileId = $participant->getProfile()->getId();
        $path = '/projects/' . $slug . '/tasks/' . $taskId->toRfc4122() . '/enroll';

        $crawler = $client->request('GET', '/projects/' . $slug . '/' . $page);
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form[action="' . $path . '"]')->form();
        self::assertNotEmpty($form->get('_token')->getValue());
        $client->submit($form);

        self::assertSame('main.projects.workPackages.enrollTask', $client->getRequest()->attributes->get('_route'));
        self::assertSame($taskId->toRfc4122(), $client->getRequest()->attributes->get('taskId'));
        self::assertResponseRedirects('/projects/' . $slug . '/work-packages', 303);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        self::assertEquals($profileId, $em->find(PackageTask::class, $taskId)->getAssignedProfile()?->getId());
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.alert-success', 'Je bent succesvol ingeschreven op deze taak.');
        self::assertSelectorTextContains('.package-task-card', 'Jij bent ingeschreven op deze taak.');
    }

    #[DataProvider('pageProvider')]
    public function testInvalidCsrfTokenDoesNotAssignTask(string $page): void
    {
        [$client, , $participant, $task] = $this->loginMember();
        $slug = $participant->getProject()->getSlug();
        $crawler = $client->request('GET', '/projects/' . $slug . '/' . $page);
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form[action$="/' . $task->getId()->toRfc4122() . '/enroll"]')->form();
        $client->submit($form, ['_token' => 'invalid-token']);

        self::assertResponseRedirects('/projects/' . $slug . '/work-packages', 303);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        self::assertNull($em->find(PackageTask::class, $task->getId())->getAssignedProfile());
        $client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'Ongeldige aanvraag.');
    }

    public static function pageProvider(): iterable
    {
        yield 'task page' => ['tasks'];
        yield 'work package page' => ['work-packages'];
    }

    #[DataProvider('closedProjectProvider')]
    public function testStaleEnrollmentFormCannotAssignATaskInAClosedProject(string $page, string $state): void
    {
        [$client, , $participant, $task] = $this->loginMember();
        $slug = $participant->getProject()->getSlug();
        $crawler = $client->request('GET', '/projects/' . $slug . '/' . $page);
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form[action$="/' . $task->getId()->toRfc4122() . '/enroll"]')->form();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $project = $em->find(Project::class, $participant->getProject()->getId());
        $status = (new Status())->setScope('project')->setName($state)->setColourHex('#10B981');
        $em->persist($status);
        $project->setStatus($status);
        $em->flush();
        $before = $em->getConnection()->fetchAllAssociative('SELECT * FROM package_tasks ORDER BY id');

        $client->submit($form);

        self::assertResponseRedirects('/projects/' . $slug . '/work-packages', 303);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertSame($before, $em->getConnection()->fetchAllAssociative('SELECT * FROM package_tasks ORDER BY id'));
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.alert-warning', 'Dit project staat geen taakinschrijvingen meer toe.');
    }

    public static function closedProjectProvider(): iterable
    {
        foreach (self::pageProvider() as [$page]) {
            foreach (['completed', 'archived', 'withdrawn'] as $state) {
                yield "$page / $state" => [$page, $state];
            }
        }
    }

    public function testPreviouslyLoadedTaskCannotOverwriteALaterAssignment(): void
    {
        [, $em, $participant, $task] = $this->loginMember();
        $otherProfile = $participant->getProject()->getOwnerAccount()->getProfile();
        $em->getConnection()->executeStatement(
            'UPDATE package_tasks SET assigned_profile_id = ? WHERE id = ?',
            [$otherProfile->getId(), $task->getId()], ['ulid', 'ulid'],
        );
        self::assertNull($task->getAssignedProfile());
        $before = $em->getConnection()->fetchAllAssociative('SELECT * FROM package_tasks ORDER BY id');

        $result = static::getContainer()->get(WorkPackageTaskEnrollmentService::class)->enroll(
            $participant->getProject(), $task->getId(), $participant->getProfile(),
        );

        self::assertSame(WorkPackageTaskEnrollmentStatus::AlreadyAssignedToOtherProfile, $result->status);
        self::assertEquals($otherProfile->getId(), $result->packageTask?->getAssignedProfile()?->getId());
        $em->flush();
        self::assertSame($before, $em->getConnection()->fetchAllAssociative('SELECT * FROM package_tasks ORDER BY id'));
    }

    #[DataProvider('unavailableTaskProvider')]
    public function testStaleFormCannotBypassTaskAvailabilityOrProjectAccess(string $page, string $state): void
    {
        [$client, , $participant, $task] = $this->loginMember();
        $slug = $participant->getProject()->getSlug();
        $taskId = $task->getId();
        $crawler = $client->request('GET', '/projects/' . $slug . '/' . $page);
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form[action$="/' . $taskId->toRfc4122() . '/enroll"]')->form();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $task = $em->find(PackageTask::class, $taskId);
        if ($state === 'deleted task') {
            $task->softDelete();
        } elseif ($state === 'deleted work package') {
            $task->getWorkPackage()->softDelete();
        } elseif ($state === 'left project') {
            $em->find(ProjectParticipant::class, $participant->getId())->leave();
        } else {
            $project = $task->getWorkPackage()->getProject();
            $other = (new Project())->setOwnerAccount($project->getOwnerAccount())
                ->setOwnerOrganisation($project->getOwnerOrganisation())->setStatus($project->getStatus())
                ->setTitle('Other enrollment project')->setSlug('review-enrollment-other')->setVisibility('private')
                ->setSummary('Other')->setDescription('Other')->setStartDate(new \DateTimeImmutable())->setCapacity(5);
            $package = (new WorkPackage())->setProject($other)->setStatus($task->getWorkPackage()->getStatus())
                ->setTitle('Foreign enrollment package')->setSlug('review-enrollment-other');
            $em->persist($other);
            $em->persist($package);
            $task->setWorkPackage($package);
        }
        $em->flush();
        $before = $em->getConnection()->fetchAllAssociative('SELECT * FROM package_tasks ORDER BY id');
        $client->catchExceptions(true);
        $client->submit($form);

        self::assertResponseStatusCodeSame($state === 'left project' ? 403 : 404);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertSame($before, $em->getConnection()->fetchAllAssociative('SELECT * FROM package_tasks ORDER BY id'));
    }

    public static function unavailableTaskProvider(): iterable
    {
        foreach (self::pageProvider() as [$page]) {
            foreach (['deleted task', 'deleted work package', 'other project', 'left project'] as $state) {
                yield $page . ' / ' . $state => [$page, $state];
            }
        }
    }

    public function testTaskLookupSupportsExistingIdRepresentationsAndRejectsInvalidIds(): void
    {
        [, , $participant, $task] = $this->loginMember();
        $repository = static::getContainer()->get(PackageTaskRepository::class);
        foreach ([$task->getId(), (string) $task->getId(), $task->getId()->toRfc4122()] as $id) {
            self::assertSame($task, $repository->findOneForProject($id, $participant->getProject()));
        }
        self::assertNull($repository->findOneForProject('not-a-task-id', $participant->getProject()));
    }

    /** @return array{KernelBrowser, EntityManagerInterface, ProjectParticipant, PackageTask} */
    private function loginMember(): array
    {
        $client = static::createClient(['debug' => false]);
        $client->catchExceptions(false);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $account = $em->getRepository(Account::class)->findOneBy(['username' => 'TestUser']);
        $participant = $em->getRepository(ProjectParticipant::class)->findOneBy(['profile' => $account->getProfile()]);
        $participant->join();
        $participant->getRole()->setPermissionsMask(ProjectPermission::VIEW->value);
        // Enrollment requires membership and view rights, not ownership or organisation management.
        $project = $participant->getProject();
        $project->setOwnerAccount($em->getRepository(Account::class)->findOneBy(['username' => 'AdminUser']));
        $package = $em->getRepository(WorkPackage::class)->findOneBy(['project' => $project]);
        $status = $em->getRepository(Status::class)->findOneBy(['scope' => 'PackageTask', 'name' => 'active']);
        $task = (new PackageTask())->setWorkPackage($package)->setStatus($status)
            ->setTitle('Enrollment review task')->setSlug('review-enrollment')->setPriority('normal');
        $em->persist($task);
        $em->flush();
        $client->loginUser($account);

        return [$client, $em, $participant, $task];
    }
}
