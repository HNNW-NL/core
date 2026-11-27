<?php

namespace App\Tests;

use App\Entity\Account\Account;
use App\Entity\Org\Organisation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class OrgProjectsViewTest extends WebTestCase
{
    use \Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

    protected static function getKernelClass(): string
    {
        return \App\Kernel::class;
    }

    public function testOwnerCanViewOrganisationProjects(): void
    {
        $client = static::createClient();

        $em = static::getContainer()->get(EntityManagerInterface::class);

        $account = $em->getRepository(Account::class)
            ->findOneBy(['username' => 'TestUser']);

        $organisation = $em->getRepository(Organisation::class)
            ->findOneBy(['name' => 'Test Organistion']);

        self::assertNotNull($account);
        self::assertNotNull($organisation);

        $client->loginUser($account);

        $client->request(
            'GET',
            '/org/projects?organisation='
                . $organisation->getId()->toRfc4122()
        );

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.projects-container');
        self::assertSelectorExists('.project-card');
        self::assertSelectorTextContains(
            '.project-card .project-title',
            'Test Project'
        );
    }

    public function testAccountCentreProjectsRemainAccessible(): void
    {
        $client = static::createClient();

        $em = static::getContainer()->get(EntityManagerInterface::class);

        $account = $em->getRepository(Account::class)
            ->findOneBy(['username' => 'TestUser']);

        self::assertNotNull($account);

        $client->loginUser($account);
        $client->request('GET', '/account/projects');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.projects-container');
    }

    public function testProjectsAreIsolatedByOrganisation(): void
    {
        $client = static::createClient();

        $em = static::getContainer()->get(EntityManagerInterface::class);

        $account = $em->getRepository(Account::class)
            ->findOneBy(['username' => 'TestUser']);

        $organisation = $em->getRepository(Organisation::class)
            ->findOneBy(['name' => 'Test Organistion']);

        $original = $em->getRepository(\App\Entity\Project\Project::class)
            ->findOneBy(['slug' => 'Test-Project']);

        self::assertNotNull($account);
        self::assertNotNull($organisation);
        self::assertNotNull($original);

        $otherOrganisation = new Organisation();
        $otherOrganisation->setName('INT04 Isolation Organisation');
        $otherOrganisation->setSlug('int04-isolation-organisation');
        $otherOrganisation->setStatus($organisation->getStatus());

        $em->persist($otherOrganisation);

        $role = new \App\Entity\Org\OrgRole();
        $role->setName('INT04 Project Manager');
        $role->setOrganisation($otherOrganisation);
        $role->setPermissionsMask(
            \App\Security\Permission::maskFor([
                \App\Security\Permission::ORG_VIEW,
                \App\Security\Permission::ORG_MANAGE_PROJECTS,
            ])
        );

        $em->persist($role);

        $membership = new \App\Entity\Org\OrgMember();
        $membership->setAccount($account);
        $membership->setOrganisation($otherOrganisation);
        $membership->setOrgRole($role);

        $em->persist($membership);

        $otherProject = new \App\Entity\Project\Project();
        $otherProject
            ->setOwnerAccount($account)
            ->setOwnerOrganisation($otherOrganisation)
            ->setStatus($original->getStatus())
            ->setVisibility($original->getVisibility())
            ->setTitle('INT04 Other Organisation Project')
            ->setSlug('int04-other-organisation-project')
            ->setSummary('Isolation test project')
            ->setDescription('Temporary integration test project')
            ->setStartDate(new \DateTimeImmutable())
            ->setCapacity(10);

        $em->persist($otherProject);

        $deletedProject = new \App\Entity\Project\Project();
        $deletedProject
            ->setOwnerAccount($account)
            ->setOwnerOrganisation($organisation)
            ->setStatus($original->getStatus())
            ->setVisibility($original->getVisibility())
            ->setTitle('INT04 Deleted Project')
            ->setSlug('int04-deleted-project')
            ->setSummary('Deleted project test')
            ->setDescription('Temporary deleted project')
            ->setStartDate(new \DateTimeImmutable())
            ->setCapacity(10);

        $deletedProject->softDelete();

        $em->persist($deletedProject);
        $em->flush();

        $firstId = $organisation->getId()->toRfc4122();
        $secondId = $otherOrganisation->getId()->toRfc4122();

        $client->loginUser($account);

        $crawler = $client->request(
            'GET',
            '/org/projects?organisation=' . $firstId
        );

        self::assertResponseIsSuccessful();

        $titles = $crawler
            ->filter('.project-card .project-title')
            ->each(static fn ($node) => trim($node->text()));

        self::assertContains('Test Project', $titles);
        self::assertNotContains('INT04 Other Organisation Project', $titles);
        self::assertNotContains('INT04 Deleted Project', $titles);

        $crawler = $client->request(
            'GET',
            '/org/projects?organisation=' . $secondId
        );

        self::assertResponseIsSuccessful();

        $titles = $crawler
            ->filter('.project-card .project-title')
            ->each(static fn ($node) => trim($node->text()));

        self::assertSame(
            ['INT04 Other Organisation Project'],
            $titles
        );
    }

    public function testViewerCannotManageOrganisationProjects(): void
    {
        $client = static::createClient();

        $em = static::getContainer()->get(EntityManagerInterface::class);

        $account = $em->getRepository(Account::class)
            ->findOneBy(['username' => 'TestUser']);

        $original = $em->getRepository(Organisation::class)
            ->findOneBy(['name' => 'Test Organistion']);

        self::assertNotNull($account);
        self::assertNotNull($original);

        $organisation = new Organisation();
        $organisation->setName('INT04 Viewer Organisation');
        $organisation->setSlug('int04-viewer-organisation');
        $organisation->setStatus($original->getStatus());

        $em->persist($organisation);

        $role = new \App\Entity\Org\OrgRole();
        $role->setName('INT04 Viewer');
        $role->setOrganisation($organisation);
        $role->setPermissionsMask(
            \App\Security\Permission::maskFor([
                \App\Security\Permission::ORG_VIEW,
            ])
        );

        $em->persist($role);

        $membership = new \App\Entity\Org\OrgMember();
        $membership->setAccount($account);
        $membership->setOrganisation($organisation);
        $membership->setOrgRole($role);

        $em->persist($membership);
        $em->flush();

        $id = $organisation->getId()->toRfc4122();

        $client->loginUser($account);

        $client->request(
            'GET',
            '/org?organisation=' . $id
        );

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(
            'section h2',
            'INT04 Viewer Organisation'
        );

        $client->request(
            'GET',
            '/org/projects?organisation=' . $id
        );

        self::assertResponseStatusCodeSame(403);
    }

    public function testOrganisationProjectSearch(): void
    {
        $client = static::createClient();

        $em = static::getContainer()->get(EntityManagerInterface::class);

        $account = $em->getRepository(Account::class)
            ->findOneBy(['username' => 'TestUser']);

        $organisation = $em->getRepository(Organisation::class)
            ->findOneBy(['name' => 'Test Organistion']);

        self::assertNotNull($account);
        self::assertNotNull($organisation);

        $client->loginUser($account);

        $url = '/org/projects?organisation='
            . $organisation->getId()->toRfc4122();

        // Hoofdletterongevoelig zoeken.
        $client->request('GET', $url . '&q=TEST%20PROJECT');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(
            '.project-card .project-title',
            'Test Project'
        );

        // Zoeken zonder overeenkomende projecten.
        $client->request(
            'GET',
            $url . '&q=int04-no-matching-project-987654'
        );

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.project-card');
        self::assertSelectorTextContains(
            '.projects-container',
            'No projects found'
        );

        // Een lege zoekopdracht toont opnieuw het project.
        $client->request('GET', $url . '&q=');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(
            '.project-card .project-title',
            'Test Project'
        );
    }

    public function testOrganisationProjectsLiveInteractions(): void
    {
        $client = static::createClient();

        $em = static::getContainer()->get(EntityManagerInterface::class);

        $account = $em->getRepository(Account::class)
            ->findOneBy(['username' => 'TestUser']);

        $organisation = $em->getRepository(Organisation::class)
            ->findOneBy(['name' => 'Test Organistion']);

        self::assertNotNull($account);
        self::assertNotNull($organisation);

        $live = $this->createLiveComponent(
            'Org:OrganisationProjectsView',
            [
                'organisationId' => $organisation->getId()->toRfc4122(),
            ],
            $client
        )->actingAs($account, 'main');

        // Initial render.
        $crawler = $live->render()->crawler();

        self::assertSame(200, $live->response()->getStatusCode());
        self::assertStringContainsString(
            'Test Project',
            $crawler->filter('.project-card .project-title')->text()
        );

        // Interactive search without results.
        $live->set('query', 'int04-no-matching-project-987654');

        self::assertSame(200, $live->response()->getStatusCode());
        self::assertCount(
            0,
            $live->render()->crawler()->filter('.project-card')
        );

        // Interactive case-insensitive search.
        $live->set('query', 'TEST PROJECT');

        self::assertSame(200, $live->response()->getStatusCode());
        self::assertStringContainsString(
            'Test Project',
            $live->render()->crawler()
                ->filter('.project-card .project-title')->text()
        );

        // Change the maximum number of results.
        $live->set('maxResultsPerPage', 1);

        self::assertSame(200, $live->response()->getStatusCode());
        self::assertSame(1, ((object) json_decode($live->render()->crawler()->filter('[data-live-props-value]')->attr('data-live-props-value'), true, flags: JSON_THROW_ON_ERROR))->maxResultsPerPage);

        // Pagination must remain within valid boundaries.
        $live->call('nextPage');

        self::assertSame(200, $live->response()->getStatusCode());
        self::assertSame(1, ((object) json_decode($live->render()->crawler()->filter('[data-live-props-value]')->attr('data-live-props-value'), true, flags: JSON_THROW_ON_ERROR))->page);

        $live->call('previousPage');

        self::assertSame(200, $live->response()->getStatusCode());
        self::assertSame(1, ((object) json_decode($live->render()->crawler()->filter('[data-live-props-value]')->attr('data-live-props-value'), true, flags: JSON_THROW_ON_ERROR))->page);

        // Reset all interactive settings.
        $live->call('reset');

        self::assertSame(200, $live->response()->getStatusCode());

        $component = ((object) json_decode($live->render()->crawler()->filter('[data-live-props-value]')->attr('data-live-props-value'), true, flags: JSON_THROW_ON_ERROR));

        self::assertSame('', $component->query);
        self::assertSame(16, $component->maxResultsPerPage);
        self::assertSame('medium', $component->cardSize);
        self::assertSame(1, $component->page);

        self::assertStringContainsString(
            'Test Project',
            $live->render()->crawler()
                ->filter('.project-card .project-title')->text()
        );
    }

    public function testLiveComponentRejectsRevokedProjectPermissions(): void
    {
        $client = static::createClient();

        $em = static::getContainer()->get(EntityManagerInterface::class);

        $account = $em->getRepository(Account::class)
            ->findOneBy(['username' => 'TestUser']);

        $organisation = $em->getRepository(Organisation::class)
            ->findOneBy(['name' => 'Test Organistion']);

        self::assertNotNull($account);
        self::assertNotNull($organisation);

        $organisationId = $organisation->getId();

        $live = $this->createLiveComponent(
            'Org:OrganisationProjectsView',
            [
                'organisationId' => $organisationId->toRfc4122(),
            ],
            $client
        )->actingAs($account, 'main');

        // Access is initially allowed.
        self::assertSame(200, $live->response()->getStatusCode());

        self::assertStringContainsString(
            'Test Project',
            $live->render()->crawler()
                ->filter('.project-card .project-title')->text()
        );

        // Reload the entity manager after the HTTP request.
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $organisation = $em->find(
            Organisation::class,
            $organisationId
        );

        self::assertNotNull($organisation);

        $role = $em->getRepository(\App\Entity\Org\OrgRole::class)
            ->findOneBy([
                'organisation' => $organisation,
                'name' => 'Owner',
            ]);

        self::assertNotNull($role);

        // Revoke project management permissions.
        $role->setPermissionsMask(
            \App\Security\Permission::maskFor([
                \App\Security\Permission::ORG_VIEW,
            ])
        );

        $em->flush();

        // The next LiveComponent request must be denied.
        $client->catchExceptions(true);

        $live->set('query', 'Test Project');

        self::assertSame(
            403,
            $live->response()->getStatusCode()
        );
    }
}
