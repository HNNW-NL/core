<?php

namespace App\Tests\Module\Main\Controller;

use App\Entity\Account\Account;
use App\Entity\Account\Profile;
use App\Entity\Common\Status;
use App\Entity\Org\Organisation;
use App\Entity\Project\Project;
use App\Entity\Project\ProjectApplication;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ProjectApplicationFlowTest extends WebTestCase
{
    protected static function getKernelClass(): string { return Kernel::class; }

    private array $created = [];

    private function persistFixture(EntityManagerInterface $em, object $entity): void
    {
        if (!$em->contains($entity)) {
            $this->created[] = [$entity::class, $entity->getId()];
            $em->persist($entity);
        }
    }

    protected function tearDown(): void
    {
        try {
            if ($this->created !== []) {
                $em = static::getContainer()->get(EntityManagerInterface::class);
                $em->clear();
                foreach ($this->created as [$class, $id]) {
                    if ($class === Project::class) {
                        $project = $em->find(Project::class, $id);
                        foreach ($em->getRepository(ProjectApplication::class)->findBy(['project' => $project]) as $application) {
                            $em->remove($application);
                        }
                    }
                }
                $em->flush();
                foreach (array_reverse($this->created) as [$class, $id]) {
                    $entity = $em->find($class, $id);
                    if ($entity !== null) {
                        $em->remove($entity);
                        $em->flush();
                    }
                }
            }
        } finally {
            $this->created = [];
            parent::tearDown();
        }
    }

    public function testSubmitDuplicateFilterAndWithdrawThroughHttp(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $runId = bin2hex(random_bytes(8));
        $statuses = $em->getRepository(Status::class);
        $active = $statuses->findOneBy(['scope' => 'account', 'name' => 'active']) ?? (new Status())->setScope('account')->setName('active')->setColourHex('#10b981');
        $pending = $statuses->findOneBy(['scope' => 'ProjectApplication', 'name' => 'send']) ?? (new Status())->setScope('ProjectApplication')->setName('send')->setColourHex('#10b981');
        $projectStatus = $statuses->findOneBy(['scope' => 'project', 'name' => 'active']) ?? (new Status())->setScope('project')->setName('active')->setColourHex('#10b981');
        $owner = (new Account())->setUsername('owner-'.$runId)->setEmail('owner@example.test-'.$runId)->setPasswordHash('unused')->setStatus($active);
        $account = (new Account())->setUsername('applicant-'.$runId)->setEmail('applicant@example.test-'.$runId)->setPasswordHash('unused')->setStatus($active);
        $account->verifyEmail();
        $profile = (new Profile())->setAccount($account)->setFirstName('Applicant')->setLastName('Test')->setDisplayName('Applicant')->setAvatarUrl('');
        $account->setProfile($profile);
        $org = (new Organisation())->setName('Testorganisatie')->setSlug('testorg-'.$runId)->setStatus($active);
        $project = (new Project())->setOwnerAccount($owner)->setOwnerOrganisation($org)->setStatus($projectStatus)
            ->setTitle('Aanvraagproject')->setSlug('aanvraagproject-'.$runId)->setVisibility('public')->setSummary('Test')
            ->setDescription('Test')->setCapacity(10)->setStartDate(new \DateTimeImmutable())->setEndDate(null);
        $project->publish();
        foreach ([$active, $pending, $projectStatus, $owner, $account, $profile, $org, $project] as $entity) { $this->persistFixture($em, $entity); }
        // Een andere aanvraag bewijst dat de test geen lege tabel veronderstelt.
        $otherProfile = (new Profile())->setAccount($owner)->setFirstName('Other')->setLastName('Applicant')->setAvatarUrl('');
        $owner->setProfile($otherProfile);
        $this->persistFixture($em, $otherProfile);
        $this->persistFixture($em, (new ProjectApplication())->setProject($project)->setProfile($otherProfile)->setStatus($pending));
        $em->flush();
        $client->loginUser($account);
        $applyPath = '/projects/'.$project->getSlug().'/apply';
        $page = $client->request('GET', $applyPath);
        self::assertResponseIsSuccessful();
        $token = $page->filter('input[name="project_apply[_token]"]')->attr('value');
        $motivation = 'Ik wil graag bijdragen aan dit project.';
        $client->request('POST', $applyPath, ['project_apply' => ['_token' => 'invalid', 'motivation' => $motivation]]);
        self::assertResponseStatusCodeSame(422);
        $client->request('POST', $applyPath, ['project_apply' => ['_token' => $token, 'motivation' => 'Te kort']]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', '20 tekens');
        $client->request('POST', $applyPath, ['project_apply' => ['_token' => $token, 'motivation' => $motivation], 'account_id' => (string) $owner->getId()]);
        self::assertResponseRedirects($applyPath, 303);
        $client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'Je aanvraag is ingediend');
        $client->request('POST', $applyPath, ['project_apply' => ['_token' => $token, 'motivation' => $motivation]]);
        self::assertResponseRedirects($applyPath, 303);
        $client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'Je had je al aangemeld');
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertSame(1, $em->getRepository(ProjectApplication::class)->count(['project' => $project, 'profile' => $profile]));
        $application = $em->getRepository(ProjectApplication::class)->findOneBy(['project' => $project, 'profile' => $profile]);
        self::assertSame((string) $account->getId(), (string) $application->getProfile()->getAccount()->getId());
        self::assertSame($motivation, $application->getMotivation());
        $reviewRepository = static::getContainer()->get(\App\Repository\Project\ProjectParticipantRepository::class);
        self::assertNotNull($reviewRepository->findApplicationForProject((string) $application->getId(), $application->getProject()));
        $client->request('GET', $applyPath);
        self::assertSelectorTextContains('body', 'Ingediend');
        self::assertSelectorNotExists('textarea[name="project_apply[motivation]"]');
        $page = $client->request('GET', '/account/applications?status=Ingediend');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Testorganisatie');
        $form = $page->selectButton('Aanmelding annuleren')->form();
        $client->request('POST', '/account/applications/'. $application->getId() .'/cancel', ['_token' => 'invalid']);
        self::assertResponseStatusCodeSame(403);
        $client->submit($form);
        self::assertResponseRedirects('/account/applications', 303);
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Ingetrokken');
        self::assertSelectorNotExists('button[type="submit"].btn-card-cancel');
        $client->submit($form);
        self::assertResponseRedirects('/account/applications', 303);
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Deze aanmelding was al ingetrokken.');
        self::assertSelectorTextNotContains('body', 'Aanmelding ingetrokken.');
        $client->request('GET', '/account/applications?status=Ingediend');
        self::assertSelectorTextContains('body', 'Geen aanmeldingen');
        $client->request('POST', $applyPath, ['project_apply' => ['_token' => $token, 'motivation' => $motivation]]);
        self::assertResponseRedirects($applyPath, 303);
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'niet opnieuw ingediend');
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $profile = $em->find(Profile::class, $profile->getId());
        $owner = $em->find(Account::class, $owner->getId());
        $org = $em->find(Organisation::class, $org->getId());
        $pending = $em->find(Status::class, $pending->getId());
        $projectStatus = $em->find(Status::class, $projectStatus->getId());
        for ($i = 0; $i < 21; ++$i) {
            $anotherProject = (new Project())->setOwnerAccount($owner)->setOwnerOrganisation($org)->setStatus($projectStatus)
                ->setTitle('Project '.$i)->setSlug('pagination--'.$runId.$i)->setVisibility('public')->setSummary('Test')
                ->setDescription('Test')->setCapacity(10)->setStartDate(new \DateTimeImmutable())->setEndDate(null);
            $anotherApplication = (new ProjectApplication())->setProject($anotherProject)->setProfile($profile)->setStatus($pending);
            $this->persistFixture($em, $anotherProject);
            $this->persistFixture($em, $anotherApplication);
        }
        $em->flush();
        $repository = static::getContainer()->get(\App\Repository\Project\AccountApplicationRepository::class);
        $firstPage = $repository->pageForProfile($profile, 'Ingediend', 1);
        $secondPage = $repository->pageForProfile($profile, 'Ingediend', 2);
        self::assertSame(21, $firstPage['total']);
        self::assertCount(20, $firstPage['items']);
        self::assertCount(1, $secondPage['items']);
        self::assertSame(2, $secondPage['page']);
        self::assertSame(1, $repository->pageForProfile($profile, 'Ingetrokken', 1)['total']);
        self::assertSame(0, $repository->pageForProfile(new Profile(), '', 1)['total']);
        self::assertSame(0, $repository->pageForProfile($profile, 'unknown', 1)['total']);
        $page = $client->request('GET', '/account/applications?status=Ingediend');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(20, '.account-grid .account-card');
        self::assertStringNotContainsString('Pagina', $page->filter('title')->text());
        self::assertSelectorCount(1, 'nav[aria-label="Paginering aanvragen"]');
        $client->click($page->selectLink('Volgende')->link());
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(1, '.account-grid .account-card');
        self::assertSelectorTextContains('nav[aria-label="Paginering aanvragen"]', 'Pagina 2 van 2');
    }
}
