<?php

namespace App\Tests\Module\Org\Controller;

use App\Entity\Account\Account;
use App\Entity\Project\ProjectParticipant;
use App\Entity\Project\WorkPackage;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * OR06-D: rechten en fouten van de POST-route
 * /org/projects/modify/{id}/participants/{participantId}/terminate
 */
final class TerminateParticipantTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    /** @return array{0: string, 1: ProjectParticipant} */
    private function terminatePathAndParticipant(EntityManagerInterface $em): array
    {
        $package = $em->getRepository(WorkPackage::class)->findOneBy(['slug' => 'ork-package']);
        self::assertInstanceOf(WorkPackage::class, $package);
        $project = $package->getProject();
        $participant = $em->getRepository(ProjectParticipant::class)->findOneBy(['project' => $project]);
        self::assertInstanceOf(ProjectParticipant::class, $participant);

        return [
            '/org/projects/modify/' . $project->getId() . '/participants/' . $participant->getId() . '/terminate',
            $participant,
        ];
    }

    public function testGuestCannotTerminateParticipation(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        [$path, $participant] = $this->terminatePathAndParticipant($em);

        $client->request('POST', $path, ['_token' => 'x']);

        self::assertResponseRedirects();
        self::assertStringContainsString('/auth/login', (string) $client->getResponse()->headers->get('Location'));
        self::assertNull($em->find(ProjectParticipant::class, $participant->getId())->getLeftAt());
    }

    public function testOutsiderWithoutManagePermissionGetsForbidden(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        [$path, $participant] = $this->terminatePathAndParticipant($em);
        // TestUser heeft zonder ORG_MANAGE_PROJECTS geen beheerrechten (zie WorkPackageStatusTest).
        $account = $em->getRepository(Account::class)->findOneBy(['username' => 'TestUser']);
        self::assertInstanceOf(Account::class, $account);
        $client->loginUser($account);

        $client->request('POST', $path, ['_token' => 'x']);

        self::assertResponseStatusCodeSame(403);
        self::assertNull($em->find(ProjectParticipant::class, $participant->getId())->getLeftAt());
    }

    public function testGetIsNotAllowedOnTerminateRoute(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        [$path] = $this->terminatePathAndParticipant($em);
        $account = $em->getRepository(Account::class)->findOneBy(['username' => 'TestUser']);
        $client->loginUser($account);

        $client->request('GET', $path);

        self::assertResponseStatusCodeSame(405);
    }

    public function testUnknownProjectIdReturnsNotFoundOrForbiddenNeverSuccess(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $account = $em->getRepository(Account::class)->findOneBy(['username' => 'TestUser']);
        $client->loginUser($account);

        $client->request('POST', '/org/projects/modify/geen-ulid/participants/geen-ulid/terminate', ['_token' => 'x']);

        self::assertResponseStatusCodeSame(404);
    }
}
