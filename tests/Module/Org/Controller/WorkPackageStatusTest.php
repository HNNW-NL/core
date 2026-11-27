<?php

namespace App\Tests\Module\Org\Controller;

use App\Entity\Account\Account;
use App\Entity\Common\Status;
use App\Entity\Org\OrgMember;
use App\Entity\Org\OrgRole;
use App\Entity\Project\WorkPackage;
use App\Kernel;
use App\Security\Permission;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class WorkPackageStatusTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testProjectOwnerCanChangeStatusFromTheManagementPage(): void
    {
        [$client, $entityManager, $package] = $this->loginEditor();
        $project = $package->getProject();
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

        $client->catchExceptions(true);
        $client->request('POST', $statusPath, ['status' => 'Closed']);
        self::assertResponseStatusCodeSame(403);
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertSame('Assigned', $entityManager->find(WorkPackage::class, $package->getId())->getStatus()->getName());
    }

    #[DataProvider('closedStateProvider')]
    public function testStaleStatusFormCannotChangeAClosedProject(string $state): void
    {
        [$client, $em, $package] = $this->loginEditor();
        $project = $package->getProject();
        $path = '/org/projects/modify/' . $project->getId() . '/work-packages';
        $statusPath = $path . '/' . $package->getId() . '/status';
        $page = $client->request('GET', $path);
        self::assertResponseIsSuccessful();
        $form = $page->filter('form[action="' . $statusPath . '"]')->form();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $project = $em->find(WorkPackage::class, $package->getId())->getProject();
        $status = (new Status())->setScope('project')->setName($state)->setColourHex('#10B981');
        $em->persist($status);
        $project->setStatus($status);
        $em->flush();
        $before = $em->getConnection()->fetchAllAssociative('SELECT * FROM work_packages ORDER BY id');

        $client->submit($form, ['status' => 'Assigned']);

        self::assertResponseRedirects($path, 303);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertSame($before, $em->getConnection()->fetchAllAssociative('SELECT * FROM work_packages ORDER BY id'));
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[role="alert"]', 'De statuswijziging is niet mogelijk.');
        self::assertSelectorNotExists('select[name="status"]');
        self::assertSelectorTextSame('#work-package-status-' . $package->getId(), 'Open');
    }

    public static function closedStateProvider(): iterable
    {
        foreach (['completed', 'archived', 'withdrawn'] as $state) {
            yield $state => [$state];
        }
    }

    /** @return array{KernelBrowser, EntityManagerInterface, WorkPackage} */
    private function loginEditor(): array
    {
        $client = static::createClient(['debug' => false]);
        $client->catchExceptions(false);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $account = $em->getRepository(Account::class)->findOneBy(['username' => 'TestUser']);
        $package = $em->getRepository(WorkPackage::class)->findOneBy(['slug' => 'ork-package']);
        $organisation = $package->getProject()->getOwnerOrganisation();
        $role = (new OrgRole())->setOrganisation($organisation)->setName('Status test editor')
            ->setPermissionsMask(Permission::ORG_MANAGE_PROJECTS->value);
        $em->persist($role);
        $em->persist((new OrgMember())->setAccount($account)->setOrganisation($organisation)->setOrgRole($role));
        $em->flush();
        $client->loginUser($account);

        return [$client, $em, $package];
    }
}
