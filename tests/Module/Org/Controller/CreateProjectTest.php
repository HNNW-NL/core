<?php

namespace App\Tests\Module\Org\Controller;

use App\Entity\Account\Account;
use App\Entity\Account\Profile;
use App\Entity\Org\Organisation;
use App\Entity\Org\OrgRole;
use App\Entity\Project\ProjectRole;
use App\Security\Permission;
use App\Entity\Common\Status;
use App\Entity\Org\OrgMember;
use App\Entity\Project\Project;
use App\Entity\Project\ProjectParticipant;
use App\Kernel;
use App\Security\ProjectPermission;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Ulid;

final class CreateProjectTest extends WebTestCase
{
    private const PATH = '/org/projects/create';
    private array $owned = [];
    private ?Ulid $organisationId = null;

    private function persistFixture(EntityManagerInterface $em, object $entity): void
    {
        if (!$em->contains($entity)) {
            $this->owned[] = [$entity::class, $entity->getId()];
            $em->persist($entity);
        }
    }

    private function fixtureAccount(EntityManagerInterface $em): Account
    {
        $run = bin2hex(random_bytes(8));
        $status = [];
        foreach (['account', 'organisation', 'project', 'ProjectParticipant'] as $scope) {
            $name = $scope === 'project' ? 'draft' : 'active';
            $status[$scope] = $em->getRepository(Status::class)->findOneBy(['scope' => $scope, 'name' => $name])
                ?? (new Status())->setScope($scope)->setName($name)->setColourHex('#1098ad');
            $this->persistFixture($em, $status[$scope]);
        }
        $account = (new Account())->setUsername('or03-'.$run)->setEmail('or03-'.$run.'@example.test')->setPasswordHash('unused')->setStatus($status['account']);
        $profile = (new Profile())->setAccount($account)->setFirstName('Project')->setLastName('Reviewer')->setAvatarUrl('');
        $account->setProfile($profile);
        $organisation = (new Organisation())->setName('OR03 '.$run)->setSlug('or03-'.$run)->setOwnerAccount($account)->setStatus($status['organisation']);
        $role = (new OrgRole())->setOrganisation($organisation)->setName('Project manager '.$run)
            ->setPermissionsMask(Permission::ORG_VIEW->value | Permission::ORG_EDIT->value | Permission::ORG_MANAGE_PROJECTS->value);
        $membership = (new OrgMember())->setAccount($account)->setOrganisation($organisation)->setOrgRole($role);
        foreach ([$account, $profile, $organisation, $role, $membership] as $entity) {
            $this->persistFixture($em, $entity);
        }
        $em->flush();
        $this->organisationId = $organisation->getId();
        return $account;
    }

    protected function tearDown(): void
    {
        try {
            if ($this->owned !== []) {
                $em = self::getContainer()->get(EntityManagerInterface::class);
                $em->clear();
                $organisation = $em->find(Organisation::class, $this->organisationId);
                foreach ($em->getRepository(Project::class)->findBy(['ownerOrganisation' => $organisation]) as $project) {
                    foreach ($em->getRepository(ProjectParticipant::class)->findBy(['project' => $project]) as $participant) { $em->remove($participant); }
                    $em->flush();
                    foreach ($em->getRepository(ProjectRole::class)->findBy(['project' => $project]) as $role) { $em->remove($role); }
                    $em->flush();
                    $em->remove($project);
                    $em->flush();
                }
                foreach (array_reverse($this->owned) as [$class, $id]) {
                    if (($entity = $em->find($class, $id)) !== null) { $em->remove($entity); $em->flush(); }
                }
            }
        } finally {
            $this->owned = [];
            parent::tearDown();
        }
    }

    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testCreatesOnePrivateDraftAndAssignsProjectLead(): void
    {
        $client = self::createClient();
        $form = $this->openCreateForm($client);
        $input = $form['input'];
        $input['status'] = 'published';

        $client->request('POST', self::PATH, $input);
        self::assertResponseStatusCodeSame(303);
        $editUrl = $client->getResponse()->headers->get('Location');
        self::assertStringStartsWith('/org/projects/modify/', $editUrl);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $project = $entityManager->getRepository(Project::class)->findOneBy(['title' => $input['name']]);
        self::assertInstanceOf(Project::class, $project);
        self::assertSame($form['organisation'], (string) $project->getOwnerOrganisation()->getId());
        self::assertSame($input['project_lead'], (string) $project->getOwnerAccount()->getId());
        $draft = $entityManager->getRepository(Status::class)->findOneBy(['scope' => 'project', 'name' => 'draft']);
        self::assertSame($draft === null ? 'active' : 'draft', $project->getStatus()->getName());
        self::assertNull($project->getPublishedAt());
        $participant = $entityManager->getRepository(ProjectParticipant::class)->findOneBy([
            'project' => $project, 'profile' => $project->getOwnerAccount()->getProfile(),
        ]);
        self::assertInstanceOf(ProjectParticipant::class, $participant);
        self::assertTrue(ProjectPermission::EDIT->in($participant->getRole()->getPermissionsMask()));

        $editPage = $client->request('GET', $editUrl);
        self::assertResponseIsSuccessful();
        $client->submit($editPage->selectButton('Save changes')->form(['capacity' => '']));
        self::assertResponseStatusCodeSame(422);
        $currentEm = self::getContainer()->get(EntityManagerInterface::class);
        self::assertSame(10, $currentEm->find(Project::class, $project->getId())->getCapacity());

        $client->request('GET', '/org/projects');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString($input['name'], $client->getResponse()->getContent());

        $client->request('POST', self::PATH, $input);
        self::assertResponseStatusCodeSame(422);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertSame(1, $entityManager->getRepository(Project::class)->count(['title' => $input['name']]));

        $client->restart();
        $client->request('GET', '/projects');
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString($input['name'], $client->getResponse()->getContent());
    }

    public function testRejectsMissingInvalidAndUnauthorisedInput(): void
    {
        $client = self::createClient();
        $form = $this->openCreateForm($client);
        $input = $form['input'];

        foreach ([
            ['name' => '', 'summary' => '', 'description' => ''],
            ['description' => ''],
            ['capacity' => ''],
            ['capacity' => '0'],
            ['capacity' => '501'],
            ['start_date' => '2026-02-30'],
            ['start_date' => '2027-06-01', 'end_date' => '2027-05-01'],
            ['project_lead' => (string) new Ulid()],
        ] as $invalid) {
            $client->request('POST', self::PATH, array_replace($input, $invalid));
            self::assertResponseStatusCodeSame(422);
        }

        $client->request('POST', self::PATH, array_replace($input, ['_token' => 'invalid']));
        self::assertResponseStatusCodeSame(403);
        $client->request('POST', self::PATH, array_replace($input, ['organisation_id' => (string) new Ulid()]));
        self::assertResponseStatusCodeSame(403);
        $client->request('GET', self::PATH . '?organisation=' . new Ulid());
        self::assertResponseStatusCodeSame(403);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertSame(0, $entityManager->getRepository(Project::class)->count(['title' => $input['name']]));

        $client->restart();
        $client->request('POST', self::PATH, $input);
        self::assertResponseRedirects('/auth/login');
    }

    private function openCreateForm(KernelBrowser $client): array
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $account = $this->fixtureAccount($entityManager);
        self::assertInstanceOf(Account::class, $account);
        $membership = $entityManager->getRepository(OrgMember::class)->findOneBy(['account' => $account]);
        self::assertInstanceOf(OrgMember::class, $membership);
        $organisation = (string) $membership->getOrganisation()->getId();
        $client->loginUser($account);
        $page = $client->request('GET', self::PATH . '?organisation=' . $organisation);
        self::assertResponseIsSuccessful();

        return [
            'organisation' => $organisation,
            'input' => [
                'organisation_id' => $page->filter('input[name="organisation_id"]')->attr('value'),
                '_token' => $page->filter('input[name="_token"]')->attr('value'),
                'name' => 'OR03 test ' . new Ulid(),
                'summary' => 'Test project summary',
                'description' => 'Description for the OR03 test project.',
                'visibility' => 'public',
                'capacity' => '10',
                'project_lead' => (string) $account->getId(),
            ],
        ];
    }
}
