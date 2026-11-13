<?php

namespace App\Tests\Module\Org\Controller;

use App\Entity\Account\Account;
use App\Entity\Org\OrgMember;
use App\Entity\Project\WorkPackage;
use App\Kernel;
use App\Security\Permission;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class WorkPackageStatusTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testProjectOwnerCanChangeStatusFromTheManagementPage(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $account = $entityManager->getRepository(Account::class)->findOneBy(['username' => 'TestUser']);
        $package = $entityManager->getRepository(WorkPackage::class)->findOneBy(['slug' => 'ork-package']);
        self::assertInstanceOf(Account::class, $account);
        self::assertInstanceOf(WorkPackage::class, $package);
        $project = $package->getProject();
        $membership = $entityManager->getRepository(OrgMember::class)->findOneBy([
            'account' => $account,
            'organisation' => $project->getOwnerOrganisation(),
        ]);
        self::assertInstanceOf(OrgMember::class, $membership);
        $membership->getOrgRole()->setPermissionsMask(Permission::ORG_MANAGE_PROJECTS->value);
        $entityManager->flush();

        $client->loginUser($account);
        $path = '/org/projects/modify/' . $project->getId() . '/work-packages';
        $statusPath = $path . '/' . $package->getId() . '/status';
        $page = $client->request('GET', $path);
        self::assertResponseIsSuccessful();
        self::assertSame(['', 'Assigned', 'Cancelled'], $page->filter('select[name="status"] option')->each(
            static fn ($option): ?string => $option->attr('value'),
        ));

        $client->submitForm('Status opslaan', ['status' => 'Assigned']);
        self::assertResponseRedirects($path, 303);
        self::assertSame($statusPath, $client->getRequest()->getPathInfo());
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('#work-package-status-' . $package->getId(), 'Assigned');

        $token = $client->getCrawler()->filter('form[action="' . $statusPath . '"] input[name="_token"]')->attr('value');
        $client->request('POST', $statusPath, ['status' => 'Draft', '_token' => $token]);
        self::assertResponseRedirects($path, 303);
        $client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'De statuswijziging is niet mogelijk.');

        $client->request('POST', $statusPath, ['status' => 'Closed']);
        self::assertResponseStatusCodeSame(403);
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertSame('Assigned', $entityManager->find(WorkPackage::class, $package->getId())->getStatus()->getName());
    }
}
