<?php

namespace App\Tests\Security\Voter;

use App\Entity\Account\Account;
use App\Entity\Admin\Admin;
use App\Entity\Admin\AdminRole;
use App\Entity\Org\Organisation;
use App\Entity\Org\OrgMember;
use App\Entity\Org\OrgRole;
use App\Security\Permission;
use App\Security\Voter\AdminPermissionVoter;
use App\Security\Voter\OrgPermissionVoter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class B02ScopeTest extends TestCase
{
    public function testDashboardPermissionDoesNotGrantAccountManagement(): void
    {
        $account = new Account();
        $role = (new AdminRole())->setPermissionsMask(Permission::ADMIN_VIEW_DASHBOARD->value);
        $account->setAdmin((new Admin())->setAccount($account)->setAdminRole($role));
        $token = new UsernamePasswordToken($account, 'main', $account->getRoles());
        $voter = new AdminPermissionVoter();

        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, null, ['ADMIN_VIEW_DASHBOARD']));
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($token, null, ['ADMIN_MANAGE_ACCOUNTS']));
    }

    public function testOrganisationPermissionsDoNotCrossOrganisationOrPlatformBoundaries(): void
    {
        $account = new Account();
        $own = new Organisation();
        $other = new Organisation();
        $role = (new OrgRole())->setOrganisation($own)->setPermissionsMask(Permission::ORG_MANAGE_MEMBERS->value);
        $member = (new OrgMember())->setOrganisation($own)->setAccount($account)->setOrgRole($role);
        $account->getOrgMemberships()->add($member);
        $repository = $this->createStub(EntityRepository::class);
        $repository->method('findOneBy')->willReturnMap([
            [['organisation' => $own, 'account' => $account], null, $member],
            [['organisation' => $other, 'account' => $account], null, null],
        ]);
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);
        $token = new UsernamePasswordToken($account, 'main', $account->getRoles());
        $voter = new OrgPermissionVoter($em);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, $own, ['ORG_MANAGE_MEMBERS']));
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($token, $own, ['ORG_MANAGE_ROLES']));
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($token, $other, ['ORG_MANAGE_MEMBERS']));
        self::assertSame(VoterInterface::ACCESS_DENIED, (new AdminPermissionVoter())->vote($token, null, ['ADMIN_VIEW_DASHBOARD']));
    }
}
