<?php

namespace App\Tests\Module\Main\Service;

use App\Entity\Account\Account;
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
use App\Repository\Common\StatusRepository;
use App\Security\Permission;
use App\Security\ProjectPermission;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class PackageTaskCreationAccessTest extends KernelTestCase
{
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
            $this->createTask($entry, new CreatePackageTaskDTO((string) $package->getId(), 'Rejected', 'access-rejected', '', null, 'normal'));
            self::fail('Task creation must require project, work package and organisation rights.');
        } catch (AccessDeniedException) {
            self::assertSame($before, $em->getConnection()->fetchAllAssociative('SELECT * FROM package_tasks ORDER BY id'));
        }
    }

    #[DataProvider('allowedCallerProvider')]
    public function testExistingCreationPathsStillWorkForAuthorisedActors(string $entry, string $actor): void
    {
        [$em, $project, $package] = $this->prepareActor($actor);
        $this->createTask($entry, new CreatePackageTaskDTO((string) $package->getId(), 'Allowed', 'access-allowed', 'Description', null, 'normal'));
        $em->clear();
        $task = $em->getRepository(PackageTask::class)->findOneBy(['slug' => 'access-allowed']);
        self::assertInstanceOf(PackageTask::class, $task);
        self::assertEquals($package->getId(), $task->getWorkPackage()->getId());
        self::assertEquals($project->getId(), $task->getWorkPackage()->getProject()->getId());
        self::assertSame(1, $em->getRepository(PackageTask::class)->count(['slug' => 'access-allowed']));
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
            $this->createTask($entry, new CreatePackageTaskDTO((string) $package->getId(), 'Wrong project', 'access-other', '', null, 'normal'), $other);
            self::fail('The work package must belong to the supplied project.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('Werkpakket hoort niet bij dit project.', $exception->getMessage());
        }
        self::assertSame($before, $em->getConnection()->fetchAllAssociative('SELECT * FROM package_tasks ORDER BY id'));
    }

    public static function deniedCallerProvider(): iterable
    {
        foreach (['shared service', 'admin handler', 'admin service'] as $entry) {
            foreach (['anonymous', 'outsider', 'no project rights', 'no organisation rights'] as $actor) {
                yield $entry . ' / ' . $actor => [$entry, $actor];
            }
        }
    }

    public static function allowedCallerProvider(): iterable
    {
        foreach (['shared service', 'admin handler', 'admin service'] as $entry) {
            foreach (['owner', 'member'] as $actor) {
                yield $entry . ' / ' . $actor => [$entry, $actor];
            }
        }
    }

    public static function entryProvider(): iterable
    {
        foreach (['shared service', 'admin handler', 'admin service'] as $entry) {
            yield $entry => [$entry];
        }
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
            ))->create($dto, $project),
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
            ->setPermissionsMask($actor === 'no organisation rights' ? 0 : Permission::ORG_MANAGE_PROJECTS->value);
        $em->persist($role);
        $em->persist((new OrgMember())->setAccount($account)->setOrganisation($project->getOwnerOrganisation())->setOrgRole($role));
        if ($actor === 'member' || $actor === 'no project rights') {
            $project->setOwnerAccount($outsider);
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
