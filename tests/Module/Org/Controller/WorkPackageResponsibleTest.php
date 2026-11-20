<?php

namespace App\Tests\Module\Org\Controller;

use App\Entity\Account\Account;
use App\Entity\Org\OrgMember;
use App\Entity\Org\OrgRole;
use App\Entity\Project\Project;
use App\Entity\Project\ProjectParticipant;
use App\Entity\Project\WorkPackage;
use App\Kernel;
use App\Module\Admin\DTO\UpdateWorkPackageDTO;
use App\Module\Admin\Service\WorkPackageCrudService;
use App\Security\Permission;
use App\Security\ProjectPermission;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Ulid;

final class WorkPackageResponsibleTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testAssignPreserveAndClearResponsibleThroughExistingForms(): void
    {
        [$client, $em, $project, $package, $member] = $this->prepareEditor();
        $path = $this->path($project);
        $profileId = (string) $member->getProfile()->getId();
        $page = $client->request('GET', $path);
        self::assertResponseIsSuccessful();
        self::assertSame(['', $profileId], $page->filter('#create-responsible option')->each(static fn ($option) => $option->attr('value')));
        $client->submitForm('Werkpakket opslaan', [
            'title' => 'Assigned package', 'slug' => 'responsible-test', 'responsibleProfileId' => $profileId,
        ]);
        self::assertResponseRedirects($path, 303);
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.work-package-card-grid', 'Verantwoordelijke:');
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $created = $em->getRepository(WorkPackage::class)->findOneBy(['slug' => 'responsible-test']);
        self::assertSame($profileId, (string) $created->getResponsibleProfile()->getId());
        static::getContainer()->get(WorkPackageCrudService::class)->update(
            new UpdateWorkPackageDTO((string) $created->getId(), 'Legacy update', 'responsible-test', '', null),
        );
        self::assertSame($profileId, (string) $created->getResponsibleProfile()->getId());

        $page = $client->request('GET', $path);
        $form = $page->filter('form')->reduce(
            static fn ($form) => $form->filter('input[name="_action"][value="update_work_package"]')->count() > 0
                && $form->filter('input[name="workPackageId"][value="' . $created->getId() . '"]')->count() > 0,
        )->form();
        $client->submit($form, ['responsibleProfileId' => '']);
        self::assertResponseRedirects($path, 303);
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertNull($em->find(WorkPackage::class, $created->getId())->getResponsibleProfile());
    }

    #[DataProvider('invalidResponsibleProvider')]
    public function testInvalidResponsibleDoesNotPartiallySave(string $operation, string $state): void
    {
        [$client, $em, $project, $package, $member] = $this->prepareEditor();
        $candidate = $em->getRepository(Account::class)->findOneBy(['username' => 'AdminUser'])->getProfile();
        $profileId = (string) $candidate->getId();
        $participantProject = $project;
        if ($state === 'other project') {
            $participantProject = (new Project())->setOwnerAccount($project->getOwnerAccount())
                ->setOwnerOrganisation($project->getOwnerOrganisation())->setStatus($project->getStatus())
                ->setTitle('Other project')->setSlug('responsible-other')->setSummary('Other')
                ->setDescription('Other')->setStartDate(new \DateTimeImmutable())->setCapacity(5)->setVisibility('private');
            $em->persist($participantProject);
        }
        if (in_array($state, ['other project', 'pending', 'left', 'deleted profile'], true)) {
            $participant = (new ProjectParticipant())->setProject($participantProject)->setProfile($candidate)->setStatus($member->getStatus());
            if ($state !== 'pending') {
                $participant->join();
            }
            if ($state === 'left') {
                $participant->leave();
            }
            $em->persist($participant);
        }
        if ($state === 'deleted profile') {
            $candidate->softDelete();
        }
        if ($state === 'malformed') {
            $profileId = 'invalid-profile';
        }
        if ($state === 'unknown') {
            $profileId = (string) new Ulid();
        }
        $package->setResponsibleProfile($member->getProfile());
        $em->flush();
        $before = $em->getConnection()->fetchAllAssociative('SELECT * FROM work_packages ORDER BY id');
        $path = $this->path($project);
        $page = $client->request('GET', $path);
        self::assertResponseIsSuccessful();
        self::assertCount(0, $page->filter('#create-responsible option[value="' . $profileId . '"]'));
        $token = $page->filter('input[name="_token"]')->last()->attr('value');
        $client->request('POST', $path, [
            '_action' => $operation . '_work_package', '_token' => $token,
            'workPackageId' => (string) $package->getId(), 'responsibleProfileId' => $profileId,
            'title' => 'Must not be saved', 'slug' => 'responsible-invalid', 'description' => 'Must not change', 'dueDate' => '2027-01-01',
        ]);
        self::assertResponseRedirects($path, 303);
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[role="alert"]');
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertSame($before, $em->getConnection()->fetchAllAssociative('SELECT * FROM work_packages ORDER BY id'));
    }

    public static function invalidResponsibleProvider(): iterable
    {
        foreach (['create', 'update'] as $operation) {
            foreach (['other project', 'not a participant', 'pending', 'left', 'deleted profile', 'malformed', 'unknown'] as $state) {
                yield $operation . ' with ' . $state => [$operation, $state];
            }
        }
    }

    /** @return array{KernelBrowser, EntityManagerInterface, Project, WorkPackage, ProjectParticipant} */
    private function prepareEditor(): array
    {
        $client = static::createClient(['debug' => false]);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $account = $em->getRepository(Account::class)->findOneBy(['username' => 'TestUser']);
        $project = $em->getRepository(Project::class)->findOneBy(['slug' => 'Test-Project']);
        $role = (new OrgRole())->setName('Responsible test editor')->setOrganisation($project->getOwnerOrganisation())
            ->setPermissionsMask(Permission::ORG_MANAGE_PROJECTS->value);
        $em->persist($role);
        $em->persist((new OrgMember())->setAccount($account)->setOrganisation($project->getOwnerOrganisation())->setOrgRole($role));
        $participant = $em->getRepository(ProjectParticipant::class)->findOneBy(['project' => $project, 'profile' => $account->getProfile()]);
        $participant->join();
        $participant->getRole()->setPermissionsMask(ProjectPermission::VIEW->value | ProjectPermission::EDIT->value);
        $em->flush();
        $client->loginUser($account);
        return [$client, $em, $project, $em->getRepository(WorkPackage::class)->findOneBy(['project' => $project]), $participant];
    }

    private function path(Project $project): string
    {
        return '/org/projects/modify/' . $project->getId() . '/work-packages';
    }
}
