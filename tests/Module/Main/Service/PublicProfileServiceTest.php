<?php

namespace App\Tests\Module\Main\Service;

use App\Entity\Account\Account;
use App\Entity\Account\AccountSetting;
use App\Entity\Account\Profile;
use App\Module\Main\Service\PublicProfileService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class PublicProfileServiceTest extends TestCase
{
    #[TestWith(['public', true])]
    #[TestWith(['private', false])]
    #[TestWith(['connections', false])]
    public function testProfileLinksRespectVisibility(string $visibility, bool $visible): void
    {
        $account = new Account();
        $profile = (new Profile())->setAccount($account)->setDisplayName('Naam');
        $account->setSetting((new AccountSetting())->setProfileVisibility($visibility))->setUsername('username');
        $service = new PublicProfileService($this->createStub(EntityManagerInterface::class));
        self::assertSame($visible, $service->canView($profile, null));
        self::assertSame($visible ? 'username' : null, $service->summary($profile, new Account())['username']);
        self::assertTrue($service->canView($profile, $account));
        $profile->softDelete();
        self::assertFalse($service->canView($profile, $account));
    }

    public function testPrivateProfileDoesNotFetchItsSectionsForAnotherUser(): void
    {
        $account = new Account();
        $account->setProfile((new Profile())->setAccount($account))->setSetting(new AccountSetting());
        $repository = $this->createStub(EntityRepository::class);
        $repository->method('findOneBy')->willReturn($account);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);
        $em->expects(self::never())->method('createQueryBuilder');
        $service = new PublicProfileService($em);
        $this->expectException(NotFoundHttpException::class);
        $service->load('private', new Account());
    }
}
