<?php

namespace App\Tests\Module\Org\Controller;

use App\Entity\Account\Account;
use App\Entity\Common\Status;
use App\Entity\Project\PackageTask;
use App\Entity\Project\Project;
use App\Entity\Project\ProjectParticipant;
use App\Entity\Project\ProjectRole;
use App\Entity\Project\WorkPackage;
use App\Repository\Common\StatusRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PanelControllerTest extends WebTestCase
{
    // een uuid die wel geldig is maar niet in de database staat
    private const ONBEKEND_ID = '01a10c37-0000-7000-8000-000000000000';

    // hulpfuncties

    // de test client start de kernel opnieuw bij elk request, daarom vragen we de entity manager steeds opnieuw op
    private function entityManager(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function findAccount(string $email): Account
    {
        return $this->entityManager()->getRepository(Account::class)->findOneBy(['email' => $email]);
    }

    private function findTestProject(): Project
    {
        return $this->entityManager()->getRepository(Project::class)->findOneBy(['slug' => 'Test-Project']);
    }

    private function findStatus(string $scope, string $name): Status
    {
        return static::getContainer()->get(StatusRepository::class)->findOneByScopeAndName($scope, $name);
    }

    // maakt een account dat nergens lid van is en geen admin is, daarmee kunnen we de 403 testen
    private function makeOutsider(): Account
    {
        $entityManager = $this->entityManager();

        $outsider = new Account();
        $outsider->setUsername('Buitenstaander');
        $outsider->setEmail('buitenstaander@test.nl');
        $outsider->setPasswordHash('geen-echt-wachtwoord');
        $outsider->setStatus($this->findStatus('account', 'active'));

        $entityManager->persist($outsider);
        $entityManager->flush();

        return $outsider;
    }

    private function makeRole(Project $project, string $name): ProjectRole
    {
        $entityManager = $this->entityManager();

        $role = new ProjectRole();
        $role->setProject($project);
        $role->setName($name);

        $entityManager->persist($role);
        $entityManager->flush();

        return $role;
    }

    // een tweede project van dezelfde organisatie, zo kunnen we een rol maken die niet bij het testproject hoort
    private function makeSecondProject(Project $testProject): Project
    {
        $entityManager = $this->entityManager();

        $project = new Project();
        $project->setOwnerAccount($testProject->getOwnerAccount());
        $project->setOwnerOrganisation($testProject->getOwnerOrganisation());
        $project->setStatus($testProject->getStatus());
        $project->setTitle('Tweede project');
        $project->setSlug('tweede-project');
        $project->setSummary('Een tweede project');
        $project->setDescription('Alleen voor de test');
        $project->setStartDate(new \DateTimeImmutable());
        $project->setCapacity(5);
        $project->setVisibility('public');

        $entityManager->persist($project);
        $entityManager->flush();

        return $project;
    }

    // index

    public function testShouldDisplayIndex(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    // projects

    public function testShouldDisplayProjects(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    // createProject

    public function testWhenNotLoggedInShouldBeRedirectedFromCreateProject(): void
    {
        $client = static::createClient();

        $client->request('GET', '/org/projects/create');

        $this->assertResponseRedirects('/auth/login');
    }

    public function testWhenLoggedInShouldDisplayCreateProject(): void
    {
        $client = static::createClient();
        $client->loginUser($this->findAccount('Test@test.nl'));

        $client->request('GET', '/org/projects/create');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form[name="create_project"]');
    }

    public function testWhenAdminShouldDisplayCreateProject(): void
    {
        $client = static::createClient();
        $client->loginUser($this->findAccount('Admin@test.nl'));

        $client->request('GET', '/org/projects/create');

        $this->assertResponseIsSuccessful();
    }

    public function testWhenLoggedInAndSubmitFormShouldCreateProject(): void
    {
        $client = static::createClient();
        $client->loginUser($this->findAccount('Test@test.nl'));

        $crawler = $client->request('GET', '/org/projects/create');
        $form = $crawler->selectButton('Create new project')->form();

        $client->submit($form, [
            'create_project[name]' => 'Nieuw Testproject',
            'create_project[summary]' => 'Een korte samenvatting',
            'create_project[description]' => 'Een langere omschrijving',
            'create_project[capacity]' => '7',
            'create_project[visibility]' => 'public',
        ]);

        $project = $this->entityManager()->getRepository(Project::class)->findOneBy(['slug' => 'nieuw-testproject']);

        $this->assertNotNull($project);
        $this->assertSame('Nieuw Testproject', $project->getTitle());
        $this->assertSame('Test Organistion', $project->getOwnerOrganisation()->getName());
        $this->assertSame('project', $project->getStatus()->getScope());
        $this->assertSame('active', $project->getStatus()->getName());
        $this->assertSame(7, $project->getCapacity());

        // na het opslaan gaat de gebruiker naar de wijzig pagina van het nieuwe project
        $this->assertResponseRedirects();
        $this->assertStringStartsWith(
            '/org/projects/modify/' . $project->getId(),
            $client->getResponse()->headers->get('Location')
        );
    }

    public function testWhenSubmitExistingNameShouldGiveFormErrorFromCreateProject(): void
    {
        $client = static::createClient();
        $client->loginUser($this->findAccount('Test@test.nl'));

        $velden = [
            'create_project[name]' => 'Dubbel project',
            'create_project[summary]' => 'Een korte samenvatting',
            'create_project[description]' => 'Een langere omschrijving',
            'create_project[visibility]' => 'public',
        ];

        // de eerste keer mag het gewoon, de tweede keer bestaat de slug 'dubbel-project' al
        $crawler = $client->request('GET', '/org/projects/create');
        $client->submit($crawler->selectButton('Create new project')->form(), $velden);

        $this->assertResponseRedirects();

        $crawler = $client->request('GET', '/org/projects/create');
        $client->submit($crawler->selectButton('Create new project')->form(), $velden);

        $this->assertResponseStatusCodeSame(422);
        $this->assertStringContainsString('Er bestaat al een project met deze naam.', $client->getResponse()->getContent());
        $this->assertCount(1, $this->entityManager()->getRepository(Project::class)->findBy(['slug' => 'dubbel-project']));
    }

    public function testWhenSubmitWithoutVisibilityShouldGiveFormErrorFromCreateProject(): void
    {
        $client = static::createClient();
        $client->loginUser($this->findAccount('Test@test.nl'));

        $crawler = $client->request('GET', '/org/projects/create');
        $form = $crawler->selectButton('Create new project')->form();

        $client->submit($form, [
            'create_project[name]' => 'Project zonder zichtbaarheid',
            'create_project[summary]' => 'Een korte samenvatting',
            'create_project[description]' => 'Een langere omschrijving',
            'create_project[visibility]' => '',
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertStringContainsString('Kies een zichtbaarheid.', $client->getResponse()->getContent());
        $this->assertNull($this->entityManager()->getRepository(Project::class)->findOneBy(['slug' => 'project-zonder-zichtbaarheid']));
    }

    public function testWhenSubmitTooLongNameShouldGiveFormErrorFromCreateProject(): void
    {
        $client = static::createClient();
        $client->loginUser($this->findAccount('Test@test.nl'));

        $crawler = $client->request('GET', '/org/projects/create');
        $form = $crawler->selectButton('Create new project')->form();

        // de kolom title is 255 tekens, zonder de Length regel gaf dit een 500 van de database
        $client->submit($form, [
            'create_project[name]' => str_repeat('t', 256),
            'create_project[summary]' => 'Een korte samenvatting',
            'create_project[description]' => 'Een langere omschrijving',
            'create_project[visibility]' => 'public',
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertStringContainsString('De projectnaam mag maximaal 255 tekens zijn.', $client->getResponse()->getContent());
        $this->assertNull($this->entityManager()->getRepository(Project::class)->findOneBy(['slug' => str_repeat('t', 256)]));
    }

    public function testWhenAdminWithoutOrganisationSubmitsShouldGiveFormErrorFromCreateProject(): void
    {
        $client = static::createClient();
        $client->loginUser($this->findAccount('Admin@test.nl'));

        $crawler = $client->request('GET', '/org/projects/create');
        $form = $crawler->selectButton('Create new project')->form();

        $client->submit($form, [
            'create_project[name]' => 'Project van admin',
            'create_project[summary]' => 'Een korte samenvatting',
            'create_project[description]' => 'Een langere omschrijving',
            'create_project[visibility]' => 'public',
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertStringContainsString('geen lid van een organisatie', $client->getResponse()->getContent());
        $this->assertNull($this->entityManager()->getRepository(Project::class)->findOneBy(['slug' => 'project-van-admin']));
    }

    // modifyProject

    public function testWhenNotLoggedInShouldBeRedirectedFromModifyProject(): void
    {
        $client = static::createClient();
        $project = $this->findTestProject();

        $client->request('GET', '/org/projects/modify/' . $project->getId());

        $this->assertResponseRedirects('/auth/login');
    }

    public function testWhenNotProjectOwnerShouldGiveErrorFromModifyProject(): void
    {
        $client = static::createClient();
        $project = $this->findTestProject();
        $client->loginUser($this->makeOutsider());

        $client->request('GET', '/org/projects/modify/' . $project->getId());

        $this->assertResponseStatusCodeSame(403);
    }

    public function testWhenLoggedInAndProjectDoesntExistShouldGiveErrorFromModifyProject(): void
    {
        $client = static::createClient();
        $client->loginUser($this->findAccount('Test@test.nl'));

        $client->request('GET', '/org/projects/modify/' . self::ONBEKEND_ID);

        $this->assertResponseStatusCodeSame(404);
    }

    public function testWhenIdIsNotAUuidShouldGiveErrorFromModifyProject(): void
    {
        $client = static::createClient();
        $client->loginUser($this->findAccount('Test@test.nl'));

        $client->request('GET', '/org/projects/modify/dit-is-geen-uuid');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testWhenNotProjectOwnerAndProjectExistAndFormSubmitShouldGiveErrorFromModifyProject(): void
    {
        $client = static::createClient();
        $project = $this->findTestProject();
        $client->loginUser($this->makeOutsider());

        $client->request('POST', '/org/projects/modify/' . $project->getId(), [
            'modify_project' => ['title' => 'Mag niet'],
        ]);

        $this->assertResponseStatusCodeSame(403);
        $this->assertSame('Test Project', $this->findTestProject()->getTitle());
    }

    public function testWhenProjectOwnerAndProjectExistShouldDisplayModifyProject(): void
    {
        $client = static::createClient();
        $project = $this->findTestProject();
        $client->loginUser($this->findAccount('Test@test.nl'));

        $client->request('GET', '/org/projects/modify/' . $project->getId());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form[name="modify_project"]');
    }

    public function testWhenAdminShouldDisplayModifyProject(): void
    {
        $client = static::createClient();
        $project = $this->findTestProject();
        $client->loginUser($this->findAccount('Admin@test.nl'));

        $client->request('GET', '/org/projects/modify/' . $project->getId());

        $this->assertResponseIsSuccessful();
    }

    public function testWhenProjectOwnerAndSubmitFormShouldModifyProject(): void
    {
        $client = static::createClient();
        $project = $this->findTestProject();
        $projectId = (string) $project->getId();
        $client->loginUser($this->findAccount('Test@test.nl'));

        $crawler = $client->request('GET', '/org/projects/modify/' . $projectId);
        $form = $crawler->selectButton('Modify project')->form();

        $client->submit($form, [
            'modify_project[title]' => 'Aangepaste titel',
            'modify_project[summary]' => 'Aangepaste samenvatting',
            'modify_project[visibility]' => 'private',
            'modify_project[startDate]' => '2026-11-01',
            'modify_project[endDate]' => '2026-12-31',
            'modify_project[capacity]' => '12',
        ]);

        $this->assertResponseRedirects();
        $this->assertStringContainsString('status=success', $client->getResponse()->headers->get('Location'));

        $project = $this->findTestProject();

        $this->assertSame('Aangepaste titel', $project->getTitle());
        $this->assertSame('Aangepaste samenvatting', $project->getSummary());
        $this->assertSame('private', $project->getVisibility());
        $this->assertSame('2026-11-01', $project->getStartDate()->format('Y-m-d'));
        $this->assertSame('2026-12-31', $project->getEndDate()->format('Y-m-d'));
        $this->assertSame(12, $project->getCapacity());
    }

    public function testWhenEndDateIsBeforeStartDateShouldGiveFormErrorFromModifyProject(): void
    {
        $client = static::createClient();
        $project = $this->findTestProject();
        $projectId = (string) $project->getId();
        $client->loginUser($this->findAccount('Test@test.nl'));

        $crawler = $client->request('GET', '/org/projects/modify/' . $projectId);
        $form = $crawler->selectButton('Modify project')->form();

        // de pagina belooft dat de einddatum op of na de startdatum ligt, de controller controleert dat zelf
        $client->submit($form, [
            'modify_project[title]' => 'Niet opslaan',
            'modify_project[startDate]' => '2026-12-01',
            'modify_project[endDate]' => '2026-01-01',
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertStringContainsString('De einddatum moet op of na de startdatum liggen.', $client->getResponse()->getContent());
        $this->assertSame('Test Project', $this->findTestProject()->getTitle());
    }

    public function testWhenLastModifiedIsOldShouldGiveErrorAndNotModifyProject(): void
    {
        $client = static::createClient();
        $project = $this->findTestProject();
        $projectId = (string) $project->getId();
        $client->loginUser($this->findAccount('Test@test.nl'));

        $crawler = $client->request('GET', '/org/projects/modify/' . $projectId);
        $form = $crawler->selectButton('Modify project')->form();

        // het verborgen veld zegt wanneer het project voor het laatst is opgeslagen, een oude waarde betekent dat iemand anders ondertussen heeft opgeslagen
        $client->submit($form, [
            'modify_project[lastModified]' => '2000-01-01 00:00:00',
            'modify_project[title]' => 'Niet opslaan',
        ]);

        $this->assertResponseRedirects();
        $this->assertStringContainsString('status=error', $client->getResponse()->headers->get('Location'));
        $this->assertSame('Test Project', $this->findTestProject()->getTitle());
    }

    public function testWhenIntentIsDeleteShouldSoftDeleteProjectAndThenGiveNotFound(): void
    {
        $client = static::createClient();
        $project = $this->findTestProject();
        $projectId = (string) $project->getId();
        $client->loginUser($this->findAccount('Test@test.nl'));

        $crawler = $client->request('GET', '/org/projects/modify/' . $projectId);
        $form = $crawler->selectButton('Delete project')->form();

        $client->submit($form);

        $this->assertResponseRedirects('/org/projects');
        $this->assertNotNull($this->findTestProject()->getDeletedAt());

        $client->request('GET', '/org/projects/modify/' . $projectId);

        $this->assertResponseStatusCodeSame(404);
    }

    // modifyProjectMatching

    public function testWhenNotLoggedInShouldBeRedirectedFromModifyProjectMatching(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenNotProjectOwnerShouldGiveErrorFromModifyProjectMatching(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenLoggedInAndProjectDoesntExistShouldGiveErrorFromModifyProjectMatching(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenNotProjectOwnerAndProjectExistAndFormSubmitShouldGiveErrorFromModifyProjectMatching(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenProjectOwnerAndProjectExistShouldDisplayModifyProjectMatching(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenProjectOwnerAndSubmitFormShouldModifyProjectMatching(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }


    // modifyProjectParticipants

    public function testWhenNotLoggedInShouldBeRedirectedFromModifyProjectParticipants(): void
    {
        $client = static::createClient();
        $project = $this->findTestProject();

        $client->request('GET', '/org/projects/modify/' . $project->getId() . '/participants');

        $this->assertResponseRedirects('/auth/login');
    }

    public function testWhenNotProjectOwnerShouldGiveErrorFromModifyProjectParticipants(): void
    {
        $client = static::createClient();
        $project = $this->findTestProject();
        $client->loginUser($this->makeOutsider());

        $client->request('GET', '/org/projects/modify/' . $project->getId() . '/participants');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testWhenLoggedInAndProjectDoesntExistShouldGiveErrorFromModifyProjectParticipants(): void
    {
        $client = static::createClient();
        $client->loginUser($this->findAccount('Test@test.nl'));

        $client->request('GET', '/org/projects/modify/' . self::ONBEKEND_ID . '/participants');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testWhenNotProjectOwnerAndProjectExistAndFormSubmitShouldGiveErrorFromModifyProjectParticipants(): void
    {
        $client = static::createClient();
        $project = $this->findTestProject();
        $client->loginUser($this->makeOutsider());

        $client->request('POST', '/org/projects/modify/' . $project->getId() . '/participants', [
            'participant_roles' => ['role' => []],
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testWhenProjectOwnerAndProjectExistShouldDisplayModifyProjectParticipants(): void
    {
        $client = static::createClient();
        $project = $this->findTestProject();
        $client->loginUser($this->findAccount('Test@test.nl'));

        $client->request('GET', '/org/projects/modify/' . $project->getId() . '/participants');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form[name="participant_roles"]');
    }

    public function testWhenAdminShouldDisplayModifyProjectParticipants(): void
    {
        $client = static::createClient();
        $project = $this->findTestProject();
        $client->loginUser($this->findAccount('Admin@test.nl'));

        $client->request('GET', '/org/projects/modify/' . $project->getId() . '/participants');

        $this->assertResponseIsSuccessful();
    }

    public function testWhenProjectOwnerAndSubmitFormShouldModifyProjectParticipants(): void
    {
        $client = static::createClient();
        $project = $this->findTestProject();
        $projectId = (string) $project->getId();
        $client->loginUser($this->findAccount('Test@test.nl'));

        // de fixtures hebben maar een rol, dus we maken er een bij om naar te wisselen
        $testerRole = $this->makeRole($project, 'Tester');
        $testerRoleId = (string) $testerRole->getId();
        $participant = $this->entityManager()->getRepository(ProjectParticipant::class)->findOneBy(['project' => $project]);
        $participantId = (string) $participant->getId();

        $crawler = $client->request('GET', '/org/projects/modify/' . $projectId . '/participants');
        $form = $crawler->selectButton('Submit Changes')->form();

        $client->submit($form, [
            'participant_roles[role][' . $participantId . ']' => $testerRoleId,
        ]);

        $this->assertResponseRedirects('/org/projects/modify/' . $projectId . '/participants');

        $participant = $this->entityManager()->getRepository(ProjectParticipant::class)->find($participantId);

        $this->assertSame($testerRoleId, (string) $participant->getRole()->getId());
    }

    public function testWhenSubmitRoleOfOtherProjectShouldGiveErrorFromModifyProjectParticipants(): void
    {
        $client = static::createClient();
        $project = $this->findTestProject();
        $projectId = (string) $project->getId();
        $client->loginUser($this->findAccount('Test@test.nl'));

        $otherRole = $this->makeRole($this->makeSecondProject($project), 'Rol van ander project');
        $participant = $this->entityManager()->getRepository(ProjectParticipant::class)->findOneBy(['project' => $project]);
        $participantId = (string) $participant->getId();
        $oldRoleId = (string) $participant->getRole()->getId();

        $crawler = $client->request('GET', '/org/projects/modify/' . $projectId . '/participants');
        $token = $crawler->filter('input[name="participant_roles[_token]"]')->attr('value');

        // deze rol staat niet in de keuzelijst, dus de crawler kan hem niet kiezen en we posten de velden zelf
        $client->request('POST', '/org/projects/modify/' . $projectId . '/participants', [
            'participant_roles' => [
                'role' => [$participantId => (string) $otherRole->getId()],
                '_token' => $token,
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);

        $participant = $this->entityManager()->getRepository(ProjectParticipant::class)->find($participantId);

        $this->assertSame($oldRoleId, (string) $participant->getRole()->getId());
    }

    public function testWhenSubmitEmptyRoleShouldGiveFormErrorFromModifyProjectParticipants(): void
    {
        $client = static::createClient();
        $project = $this->findTestProject();
        $projectId = (string) $project->getId();
        $client->loginUser($this->findAccount('Test@test.nl'));

        $participant = $this->entityManager()->getRepository(ProjectParticipant::class)->findOneBy(['project' => $project]);
        $participantId = (string) $participant->getId();
        $oldRoleId = (string) $participant->getRole()->getId();

        $crawler = $client->request('GET', '/org/projects/modify/' . $projectId . '/participants');
        $token = $crawler->filter('input[name="participant_roles[_token]"]')->attr('value');

        // een lege rol staat niet in de keuzelijst, dus we posten de velden zelf; zonder de NotBlank regel gaf dit een 500 in de handler
        $client->request('POST', '/org/projects/modify/' . $projectId . '/participants', [
            'participant_roles' => [
                'role' => [$participantId => ''],
                '_token' => $token,
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertStringContainsString('Kies een rol.', $client->getResponse()->getContent());

        $participant = $this->entityManager()->getRepository(ProjectParticipant::class)->find($participantId);

        $this->assertSame($oldRoleId, (string) $participant->getRole()->getId());
    }

    // InviteProjectParticipants

    public function testWhenNotLoggedInShouldBeRedirectedFromInviteProjectParticipants(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenNotProjectOwnerShouldGiveErrorFromInviteProjectParticipants(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenLoggedInAndProjectDoesntExistShouldGiveErrorFromInviteProjectParticipants(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenNotProjectOwnerAndProjectExistAndFormSubmitShouldGiveErrorFromInviteProjectParticipants(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenProjectOwnerAndProjectExistShouldDisplayInviteProjectParticipants(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenProjectOwnerAndSubmitFormShouldInviteProjectParticipants(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }


    // modifyProjectReviews

    public function testWhenNotLoggedInShouldBeRedirectedFromModifyProjectReviews(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenProjectOwnerShouldGiveErrorFromModifyProjectReviews(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenNotProjectOwnerShouldGiveErrorFromModifyProjectReviews(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenLoggedInAndProjectDoesntExistShouldGiveErrorFromModifyProjectReviews(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenNotProjectOwnerAndProjectExistAndFormSubmitShouldGiveErrorFromModifyProjectReviews(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenProjectOwnerAndProjectExistShouldDisplayModifyProjectReviews(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenProjectOwnerAndSubmitFormShouldModifyProjectReviews(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }


    // modifyProjectTasks

    public function testWhenNotLoggedInShouldBeRedirectedFromModifyProjectTasks(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenNotProjectOwnerShouldGiveErrorModifyProjectTasks(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenLoggedInAndProjectDoesntExistShouldGiveErrorFromModifyProjectTasks(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenNotProjectOwnerAndProjectExistAndFormSubmitShouldGiveErrorFromModifyProjectTasks(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenProjectOwnerAndProjectExistShouldDisplayModifyProjectTasks(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenProjectOwnerAndSubmitFormShouldModifyProjectTasks(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    // modifyProjectUpdates

    public function testWhenNotLoggedInShouldBeRedirectedFromModifyProjectUpdates(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenNotProjectOwnerShouldGiveErrorFromModifyProjectUpdates(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenLoggedInAndProjectDoesntExistShouldGiveErrorFromModifyProjectUpdates(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenNotProjectOwnerAndProjectExistAndFormSubmitShouldGiveErrorFromModifyProjectUpdates(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenProjectOwnerAndProjectExistShouldDisplayModifyProjectUpdates(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenProjectOwnerAndSubmitFormShouldModifyProjectUpdates(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    // modifyProjectWorkPackages

    public function testWhenNotLoggedInShouldBeRedirectedFromModifyProjectWorkPackages(): void
    {
        $client = static::createClient();
        $project = $this->findTestProject();

        $client->request('GET', '/org/projects/modify/' . $project->getId() . '/work-packages');

        $this->assertResponseRedirects('/auth/login');
    }

    public function testWhenNotProjectOwnerInShouldGiveErrorFromModifyProjectWorkPackages(): void
    {
        $client = static::createClient();
        $project = $this->findTestProject();
        $client->loginUser($this->makeOutsider());

        $client->request('GET', '/org/projects/modify/' . $project->getId() . '/work-packages');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testWhenLoggedInAndProjectDoesntExistShouldGiveErrorFromModifyProjectWorkPackages(): void
    {
        $client = static::createClient();
        $client->loginUser($this->findAccount('Test@test.nl'));

        $client->request('GET', '/org/projects/modify/' . self::ONBEKEND_ID . '/work-packages');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testWhenNotProjectOwnerAndProjectExistAndFormSubmitShouldGiveErrorFromModifyProjectWorkPackages(): void
    {
        $client = static::createClient();
        $project = $this->findTestProject();
        $client->loginUser($this->makeOutsider());

        $client->request('POST', '/org/projects/modify/' . $project->getId() . '/work-packages', [
            'work_package' => ['title' => 'Mag niet', 'slug' => 'mag-niet'],
        ]);

        $this->assertResponseStatusCodeSame(403);
        $this->assertNull($this->entityManager()->getRepository(WorkPackage::class)->findOneBy(['slug' => 'mag-niet']));
    }

    public function testWhenProjectOwnerAndProjectExistShouldDisplayModifyProjectWorkPackages(): void
    {
        $client = static::createClient();
        $project = $this->findTestProject();
        $client->loginUser($this->findAccount('Test@test.nl'));

        $client->request('GET', '/org/projects/modify/' . $project->getId() . '/work-packages');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form[name="work_package"]');
        $this->assertSelectorExists('form.work-package-delete-form input[name="_token"]');
    }

    public function testWhenAdminShouldDisplayModifyProjectWorkPackages(): void
    {
        $client = static::createClient();
        $project = $this->findTestProject();
        $client->loginUser($this->findAccount('Admin@test.nl'));

        $client->request('GET', '/org/projects/modify/' . $project->getId() . '/work-packages');

        $this->assertResponseIsSuccessful();
    }

    public function testWhenProjectOwnerAndSubmitFormShouldCreateWorkPackage(): void
    {
        $client = static::createClient();
        $project = $this->findTestProject();
        $projectId = (string) $project->getId();
        $client->loginUser($this->findAccount('Test@test.nl'));

        $crawler = $client->request('GET', '/org/projects/modify/' . $projectId . '/work-packages');
        $form = $crawler->selectButton('Werkpakket opslaan')->form();

        $client->submit($form, [
            'work_package[title]' => 'Nieuw werkpakket',
            'work_package[slug]' => 'nieuw-werkpakket',
            'work_package[description]' => 'Omschrijving van het werkpakket',
            'work_package[dueDate]' => '2026-12-31',
        ]);

        $this->assertResponseRedirects('/org/projects/modify/' . $projectId . '/work-packages');

        $workPackage = $this->entityManager()->getRepository(WorkPackage::class)->findOneBy(['slug' => 'nieuw-werkpakket']);

        $this->assertNotNull($workPackage);
        $this->assertSame($projectId, (string) $workPackage->getProject()->getId());
        $this->assertSame('Nieuw werkpakket', $workPackage->getTitle());
        $this->assertSame('WorkPackage', $workPackage->getStatus()->getScope());
        $this->assertSame('active', $workPackage->getStatus()->getName());
        $this->assertSame('2026-12-31', $workPackage->getDueDate()->format('Y-m-d'));
    }

    public function testWhenSubmitExistingSlugShouldGiveFormErrorFromModifyProjectWorkPackages(): void
    {
        $client = static::createClient();
        $project = $this->findTestProject();
        $projectId = (string) $project->getId();
        $client->loginUser($this->findAccount('Test@test.nl'));

        $crawler = $client->request('GET', '/org/projects/modify/' . $projectId . '/work-packages');
        $form = $crawler->selectButton('Werkpakket opslaan')->form();

        // de slug 'ork-package' zit al in de fixtures van dit project
        $client->submit($form, [
            'work_package[title]' => 'Dubbel werkpakket',
            'work_package[slug]' => 'ork-package',
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertStringContainsString('Er bestaat al een werkpakket met deze slug in dit project.', $client->getResponse()->getContent());
        $this->assertCount(1, $this->entityManager()->getRepository(WorkPackage::class)->findBy(['slug' => 'ork-package']));
    }

    public function testWhenSubmitTaskFormShouldCreatePackageTask(): void
    {
        $client = static::createClient();
        $project = $this->findTestProject();
        $projectId = (string) $project->getId();
        $client->loginUser($this->findAccount('Test@test.nl'));

        $workPackage = $this->entityManager()->getRepository(WorkPackage::class)->findOneBy(['project' => $project, 'slug' => 'ork-package']);
        $workPackageId = (string) $workPackage->getId();
        // elk werkpakket heeft een eigen taakformulier met het id van het werkpakket in de naam
        $formName = 'package_task_' . $workPackageId;

        $crawler = $client->request('GET', '/org/projects/modify/' . $projectId . '/work-packages');
        $form = $crawler->filter('form[name="' . $formName . '"]')->form();

        $client->submit($form, [
            $formName . '[taskTitle]' => 'Nieuwe taak',
            $formName . '[taskSlug]' => 'nieuwe-taak',
            $formName . '[taskDescription]' => 'Omschrijving van de taak',
        ]);

        $this->assertResponseRedirects('/org/projects/modify/' . $projectId . '/work-packages');

        $task = $this->entityManager()->getRepository(PackageTask::class)->findOneBy(['slug' => 'nieuwe-taak']);

        $this->assertNotNull($task);
        $this->assertSame($workPackageId, (string) $task->getWorkPackage()->getId());
        $this->assertSame('Nieuwe taak', $task->getTitle());
        $this->assertSame('PackageTask', $task->getStatus()->getScope());
        $this->assertSame('active', $task->getStatus()->getName());
        $this->assertSame('normal', $task->getPriority());
    }

    public function testWhenDeleteWorkPackageWithValidTokenShouldSoftDeleteWorkPackage(): void
    {
        $client = static::createClient();
        $project = $this->findTestProject();
        $projectId = (string) $project->getId();
        $client->loginUser($this->findAccount('Test@test.nl'));

        $crawler = $client->request('GET', '/org/projects/modify/' . $projectId . '/work-packages');
        // de verwijderknop is een gewoon formulier met een verborgen csrf token, de crawler stuurt alle verborgen velden mee
        $form = $crawler->filter('form.work-package-delete-form')->first()->form();
        $workPackageId = $form->get('workPackageId')->getValue();

        $client->submit($form);

        $this->assertResponseRedirects('/org/projects/modify/' . $projectId . '/work-packages');

        $workPackage = $this->entityManager()->getRepository(WorkPackage::class)->find($workPackageId);

        $this->assertNotNull($workPackage->getDeletedAt());

        // een verwijderd werkpakket staat niet meer op de pagina
        $client->request('GET', '/org/projects/modify/' . $projectId . '/work-packages');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorNotExists('input[name="workPackageId"][value="' . $workPackageId . '"]');
    }

    public function testWhenDeleteWorkPackageWithoutTokenShouldNotDeleteWorkPackage(): void
    {
        $client = static::createClient();
        $project = $this->findTestProject();
        $projectId = (string) $project->getId();
        $client->loginUser($this->findAccount('Test@test.nl'));

        $workPackage = $this->entityManager()->getRepository(WorkPackage::class)->findOneBy(['project' => $project, 'slug' => 'ork-package']);
        $workPackageId = (string) $workPackage->getId();

        // eerst de pagina ophalen, dan stuurt de testclient bij de post zelf een Referer mee en gaat deze test alleen over het ontbrekende _token
        $client->request('GET', '/org/projects/modify/' . $projectId . '/work-packages');

        $client->request('POST', '/org/projects/modify/' . $projectId . '/work-packages', [
            '_action' => 'delete_work_package',
            'workPackageId' => $workPackageId,
        ]);

        $this->assertResponseRedirects('/org/projects/modify/' . $projectId . '/work-packages');

        $workPackage = $this->entityManager()->getRepository(WorkPackage::class)->find($workPackageId);

        $this->assertNull($workPackage->getDeletedAt());
    }

    // staff

    public function testShouldDisplayStaff(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    // viewStaff

    public function testWheStaffDoesntExistShouldGiveError(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }
    public function testWhenStaffExistShouldDisplayViewStaff(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }
}
