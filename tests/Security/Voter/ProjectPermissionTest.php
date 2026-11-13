<?php

namespace App\Tests\Security\Voter;

use App\DataFixtures\Project\ProjectFixtures;
use App\DataFixtures\Project\ProjectRoleFixtures;
use App\Entity\Account\Account;
use App\Entity\Account\Profile;
use App\Entity\Project\Project;
use App\Entity\Project\ProjectParticipant;
use App\Entity\Project\ProjectRole;
use App\Module\Main\Service\CurrentProfileProvider;
use App\Module\Org\Security\ProjectVoter as OrgProjectVoter;
use App\Repository\Project\ProjectParticipantRepository;
use App\Security\ProjectPermission;
use App\Security\Voter\ProjectVoter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class ProjectPermissionTest extends TestCase
{
    public function testProjectRoleFixtureGrantsInviteOnlyToProjectAdministrator(): void
    {
        $fixture = new ProjectRoleFixtures();
        $references = $this->getMockBuilder(\Doctrine\Common\DataFixtures\ReferenceRepository::class)
            ->disableOriginalConstructor()->onlyMethods(['getReference', 'addReference'])->getMock();
        $references->expects(self::once())->method('getReference')
            ->with(ProjectFixtures::TEST_PROJECT_REFERENCE, Project::class)->willReturn(new Project());
        $references->expects(self::exactly(2))->method('addReference');
        $fixture->setReferenceRepository($references);

        $manager = $this->createMock(\Doctrine\Persistence\ObjectManager::class);
        $roles = [];
        $manager->expects(self::exactly(2))->method('persist')->willReturnCallback(
            static function (ProjectRole $role) use (&$roles): void {
                $roles[$role->getName()] = $role->getPermissionsMask();
            },
        );
        $fixture->load($manager);

        self::assertSame([
            'Developer' => null,
            'Project Administrator' => ProjectPermission::VIEW->value | ProjectPermission::INVITE->value,
        ], $roles);
    }

    public function testEnumPreservesExistingProjectRoleBits(): void
    {
        self::assertSame(ProjectRole::PERMISSION_VIEW, ProjectPermission::VIEW->value);
        self::assertSame(ProjectRole::PERMISSION_EDIT, ProjectPermission::EDIT->value);
        self::assertSame(ProjectRole::PERMISSION_DELETE, ProjectPermission::DELETE->value);
        self::assertFalse(ProjectPermission::INVITE->in(
            ProjectRole::PERMISSION_VIEW | ProjectRole::PERMISSION_EDIT | ProjectRole::PERMISSION_DELETE,
        ));
    }

    public static function masks(): iterable
    {
        yield 'delete only' => [ProjectRole::PERMISSION_DELETE, false, true];
        yield 'invite only' => [ProjectPermission::INVITE->value, true, false];
        yield 'no mask' => [null, false, false];
    }

    #[DataProvider('masks')]
    public function testProjectPermissionsAreSeparateAndObjectScoped(?int $mask, bool $invite, bool $delete): void
    {
        $profile = new Profile();
        $account = (new Account())->setProfile($profile);
        $project = new Project();
        $otherProject = new Project();
        $role = (new ProjectRole())->setPermissionsMask($mask);
        $participant = (new ProjectParticipant())->setRole($role);
        $repository = $this->createStub(ProjectParticipantRepository::class);
        foreach (['findActiveParticipant', 'findLatestActiveParticipantByProjectAndProfile'] as $method) {
            $repository->method($method)->willReturnMap([
                [$project, $profile, $participant],
                [$otherProject, $profile, null],
            ]);
        }
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($account);
        $token = new UsernamePasswordToken($account, 'main', $account->getRoles());
        $inviteVoter = new OrgProjectVoter(new CurrentProfileProvider($security), $repository);
        $deleteVoter = new ProjectVoter($repository);

        self::assertSame($invite ? VoterInterface::ACCESS_GRANTED : VoterInterface::ACCESS_DENIED, $inviteVoter->vote($token, $project, [OrgProjectVoter::INVITE]));
        self::assertSame($delete ? VoterInterface::ACCESS_GRANTED : VoterInterface::ACCESS_DENIED, $deleteVoter->vote($token, $project, [ProjectVoter::DELETE]));
        self::assertSame(VoterInterface::ACCESS_DENIED, $inviteVoter->vote($token, $otherProject, [OrgProjectVoter::INVITE]));
        self::assertSame(VoterInterface::ACCESS_DENIED, $deleteVoter->vote($token, $otherProject, [ProjectVoter::DELETE]));
    }
}
