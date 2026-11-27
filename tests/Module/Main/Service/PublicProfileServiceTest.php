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
    #[TestWith(['/uploads/avatar.jpg', '/uploads/avatar.jpg'])]
    #[TestWith(['https://example.test/avatar.jpg', null])]
    #[TestWith(['//example.test/avatar.jpg', null])]
    #[TestWith(['javascript:alert(1)', null])]
    #[TestWith(['/\\example.test/avatar.jpg', null])]
    public function testAvatarUrlsAndMissingDisplayName(string $url, ?string $expected): void
    {
        $account = (new Account())->setUsername('profielnaam');
        $profile = (new Profile())->setAccount($account)->setDisplayName('')->setAvatarUrl($url);
        $summary = (new PublicProfileService($this->createStub(EntityManagerInterface::class)))->summary($profile, $account);
        self::assertSame('profielnaam', $summary['name']);
        self::assertSame($expected, $summary['avatar']);
    }

    public function testBiographyFollowsProfileVisibilityWithoutASeparateSectionBit(): void
    {
        $account = new Account();
        $settings = (new AccountSetting())->setProfileVisibility('public');
        $account->setProfile((new Profile())->setAccount($account)->setDescription('Openbare biografie'))->setSetting($settings);
        $repository = $this->createStub(EntityRepository::class);
        $repository->method('findOneBy')->willReturn($account);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);
        $query = $this->createStub(\Doctrine\ORM\Query::class);
        foreach (['setParameters', 'setFirstResult', 'setMaxResults'] as $method) {
            $query->method($method)->willReturnSelf();
        }
        $query->method('getResult')->willReturn([]);
        $query->method('getArrayResult')->willReturn([]);
        $query->method('getSingleScalarResult')->willReturn(0);
        $em->method('createQuery')->willReturn($query);
        $em->expects(self::exactly(4))->method('createQueryBuilder')->willReturnCallback(fn () => new \Doctrine\ORM\QueryBuilder($em));
        $service = new PublicProfileService($em);
        self::assertSame('Openbare biografie', $service->load('public', null)['biography']);
        $settings->setProfileVisibility('private');
        $this->expectException(NotFoundHttpException::class);
        $service->load('public', null);
    }

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
        $profile->setAvatarUrl('https://example.test/private-avatar.jpg');
        $summary = $service->summary($profile, new Account());
        self::assertSame($visible ? 'Naam' : 'Afgeschermd profiel', $summary['name']);
        self::assertSame(null, $summary['avatar']);
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
