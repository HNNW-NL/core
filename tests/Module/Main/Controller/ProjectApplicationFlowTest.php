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

    public function testSubmitDuplicateFilterAndWithdrawThroughHttp(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $active = (new Status())->setScope('account')->setName('active')->setColourHex('#10b981');
        $pending = (new Status())->setScope('ProjectApplication')->setName('send')->setColourHex('#10b981');
        $projectStatus = (new Status())->setScope('project')->setName('active')->setColourHex('#10b981');
        $owner = (new Account())->setUsername('owner')->setEmail('owner@example.test')->setPasswordHash('unused')->setStatus($active);
        $account = (new Account())->setUsername('applicant')->setEmail('applicant@example.test')->setPasswordHash('unused')->setStatus($active);
        $account->verifyEmail();
        $profile = (new Profile())->setAccount($account)->setFirstName('Applicant')->setLastName('Test')->setDisplayName('Applicant')->setAvatarUrl('');
        $account->setProfile($profile);
        $org = (new Organisation())->setName('Testorganisatie')->setSlug('testorg')->setStatus($active);
        $project = (new Project())->setOwnerAccount($owner)->setOwnerOrganisation($org)->setStatus($projectStatus)
            ->setTitle('Aanvraagproject')->setSlug('aanvraagproject')->setVisibility('public')->setSummary('Test')
            ->setDescription('Test')->setCapacity(10)->setStartDate(new \DateTimeImmutable())->setEndDate(null);
        $project->publish();
        foreach ([$active, $pending, $projectStatus, $owner, $account, $profile, $org, $project] as $entity) { $em->persist($entity); }
        $em->flush();
        $client->loginUser($account);
        $page = $client->request('GET', '/projects/aanvraagproject/apply');
        self::assertResponseIsSuccessful();
        $token = $page->filter('input[name="_token"]')->attr('value');
        $motivation = 'Ik wil graag bijdragen aan dit project.';
        $client->request('POST', '/projects/aanvraagproject/apply', ['_token' => 'invalid', 'motivation' => $motivation]);
        self::assertResponseStatusCodeSame(403);
        $client->request('POST', '/projects/aanvraagproject/apply', ['_token' => $token, 'motivation' => 'Te kort']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', '20 tot en met 2.000');
        $client->request('POST', '/projects/aanvraagproject/apply', ['_token' => $token, 'motivation' => $motivation, 'account_id' => (string) $owner->getId()]);
        self::assertResponseRedirects('/account/applications', 303);
        $client->request('POST', '/projects/aanvraagproject/apply', ['_token' => $token, 'motivation' => $motivation]);
        self::assertResponseRedirects('/account/applications', 303);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertSame(1, $em->getRepository(ProjectApplication::class)->count([]));
        $application = $em->getRepository(ProjectApplication::class)->findOneBy([]);
        self::assertSame((string) $account->getId(), (string) $application->getProfile()->getAccount()->getId());
        self::assertSame($motivation, $application->getMotivation());
        $connection = $em->getConnection();
        $connection->beginTransaction();
        try {
            $connection->executeStatement('INSERT INTO project_applications (id, project_id, profile_id, status_id, motivation, last_modified) SELECT gen_random_uuid(), project_id, profile_id, status_id, motivation, last_modified FROM project_applications');
            self::fail('De database moet een tweede aanvraag voor hetzelfde profiel en project weigeren.');
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException) {
            $connection->rollBack();
        }
        self::assertSame(1, $em->getRepository(ProjectApplication::class)->count([]));
        $reviewRepository = static::getContainer()->get(\App\Repository\Project\ProjectParticipantRepository::class);
        self::assertNotNull($reviewRepository->findApplicationForProject((string) $application->getId(), $application->getProject()));
        $client->request('GET', '/projects/aanvraagproject/apply');
        self::assertSelectorTextContains('body', 'Ingediend');
        self::assertSelectorNotExists('textarea[name="motivation"]');
        $page = $client->request('GET', '/account/applications?status=Ingediend');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Testorganisatie');
        $form = $page->selectButton('Aanvraag intrekken')->form();
        $client->request('POST', '/account/applications/'. $application->getId() .'/cancel', ['_token' => 'invalid']);
        self::assertResponseStatusCodeSame(403);
        $client->submit($form);
        self::assertResponseRedirects('/account/applications', 303);
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Ingetrokken');
        self::assertSelectorNotExists('button[type="submit"].btn-card-cancel');
        $client->request('GET', '/account/applications?status=Ingediend');
        self::assertSelectorTextContains('body', 'Geen aanmeldingen gevonden.');
    }
}
