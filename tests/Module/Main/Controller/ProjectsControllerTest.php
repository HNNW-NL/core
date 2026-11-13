<?php

namespace App\Tests\Module\Main\Controller;

use App\Entity\Account\Account;
use App\Entity\Project\ProjectApplication;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ProjectsControllerTest extends WebTestCase
{
    // index
    public function testShouldDiplayProjects(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testShouldDiplayProjectsSummary(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testShouldDiplayProjectsStatus(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    // details
    public function testShouldDiplayProjectTitle(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testShouldDiplayProjectDescription(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testShouldDiplayProjectStatus(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testShouldDiplayProjectStartdate(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testShouldDiplayProjectEndDate(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testShouldDiplayProjectCapacity(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testShouldDiplayIfRemoteIsPossible(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testShouldDiplayOwner(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testShouldDiplayOwnerOrginasation(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    // apply

    public function testWhenNotLoggedInShouldBeRedirectedFromApply(): void
    {
        $client = static::createClient();
        $client->request('GET', '/projects/Test-Project/apply');

        $this->assertResponseRedirects('/auth/login');
    }

    public function testWhenProjectDoesntExistApplyShouldGiveNotFound(): void
    {
        $client = static::createClient();
        $client->request('GET', '/projects/bestaat-niet/apply');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testShouldBeAbleToSendApplyForm(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $account = $entityManager->getRepository(Account::class)->findOneBy(['username' => 'AdminUser']);
        $client->loginUser($account);

        $client->request('GET', '/projects/Test-Project/apply');
        $this->assertResponseIsSuccessful();

        $client->submitForm('Aanmelding versturen', [
            'project_apply[motivation]' => 'ik wil graag meedoen aan dit project',
        ]);
        $this->assertResponseRedirects('/projects/Test-Project/apply');

        $application = $entityManager->getRepository(ProjectApplication::class)->findOneBy([
            'profile' => $account->getProfile(),
        ]);
        $this->assertNotNull($application);
        $this->assertSame('ik wil graag meedoen aan dit project', $application->getMotivation());
    }

    public function testWhenMotivationIsEmptyShouldGiveError(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $account = $entityManager->getRepository(Account::class)->findOneBy(['username' => 'AdminUser']);
        $client->loginUser($account);

        $client->request('GET', '/projects/Test-Project/apply');
        $client->submitForm('Aanmelding versturen', [
            'project_apply[motivation]' => '',
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextContains('.form-errors', 'Vul een motivatie in.');
    }

    public function testWhenAlreadyAppliedShouldNotShowApplyForm(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $account = $entityManager->getRepository(Account::class)->findOneBy(['username' => 'AdminUser']);
        $client->loginUser($account);

        $client->request('GET', '/projects/Test-Project/apply');
        $client->submitForm('Aanmelding versturen', [
            'project_apply[motivation]' => 'ik wil graag meedoen aan dit project',
        ]);
        $client->followRedirect();

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.alert-success', 'Je aanmelding is verstuurd.');
        $this->assertSelectorNotExists('form[name="project_apply"]');
        $this->assertSelectorTextContains('.project-apply', 'Je hebt al een aanmelding voor dit project.');
    }

    public function testWhenAlreadyParticipantShouldNotShowApplyForm(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $account = $entityManager->getRepository(Account::class)->findOneBy(['username' => 'TestUser']);
        $client->loginUser($account);

        $client->request('GET', '/projects/Test-Project/apply');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorNotExists('form[name="project_apply"]');
        $this->assertSelectorTextContains('.project-apply', 'Je doet al mee aan dit project.');
    }

    // chat

    public function testShouldDiplayMessages(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testShouldBeAbleToAddMessage(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    // participants

    public function testShouldDiplayParticipants(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    // tasks

    public function testShouldDiplayTasks(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    // enrollTask

    public function testWhenNotLoggedInShouldBeRedirectedFromEnrollTask(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenLoggedInShouldBeAbleToEnroll(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    // updates

    public function testWhenNotOwnerShouldGiveError(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenOwnerShouldDisplayPage(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenOwnerAndSubmitUpdateShouldUpdateProject(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    // workPackages

    public function testWhenWorkPackagesExistShouldDisplayThem(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenWorkPackagesDoesntExistShouldDisplayNoWorkPackagesAvailable(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }
}
