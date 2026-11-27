<?php

namespace App\Tests\Security\Voter;

use App\Entity\Account\Account;
use App\Entity\Account\Profile;
use App\Entity\Project\Project;
use App\Entity\Project\ProjectParticipant;
use App\Entity\Project\ProjectRole;
use App\Repository\Project\ProjectParticipantRepository;
use App\Security\Voter\ProjectVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class ProjectVoterTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\TestWith(['inactive'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['wrong-scope'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['pending'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['not-joined'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['left'])]
    public function testInactiveRoleNeverContributesToCombinedPermissions(string $state): void
    {
        $account = (new Account())->setProfile(new Profile());
        $project = new Project();
        $active = (new ProjectParticipant())->setRole((new ProjectRole())->setProject($project)->setPermissionsMask(1))
            ->setStatus((new \App\Entity\Common\Status())->setScope('ProjectParticipant')->setName('active'));
        $active->join();
        $invalid = (new ProjectParticipant())->setRole((new ProjectRole())->setProject($project)->setPermissionsMask(4))
            ->setStatus((new \App\Entity\Common\Status())->setScope($state === 'wrong-scope' ? 'ProjectApplication' : 'ProjectParticipant')
                ->setName(match ($state) { 'inactive' => 'inactive', 'pending' => 'send', default => 'active' }));
        if ($state !== 'not-joined') { $invalid->join(); }
        if ($state === 'left') { $invalid->leave(); }
        $repository = $this->createStub(ProjectParticipantRepository::class);
        $repository->method('findActiveParticipantsByProjectAndProfile')->willReturn([$active, $invalid]);
        $voter = new ProjectVoter($repository);
        $token = new UsernamePasswordToken($account, 'main', $account->getRoles());
        self::assertSame(1, $voter->vote($token, $project, [ProjectVoter::VIEW]));
        self::assertSame(-1, $voter->vote($token, $project, [ProjectVoter::DELETE]));
    }

    public function testCombinedRolesDoNotGrantPermissionsFromAnotherProject(): void
    {
        $account = (new Account())->setProfile(new Profile());
        $project = new Project();
        $other = new Project();
        $participants = [
            (new ProjectParticipant())->setRole((new ProjectRole())->setProject($project)->setPermissionsMask(1)),
            (new ProjectParticipant())->setRole((new ProjectRole())->setProject($project)->setPermissionsMask(2)),
            (new ProjectParticipant())->setRole((new ProjectRole())->setProject($other)->setPermissionsMask(4)),
        ];
        foreach ($participants as $participant) { $participant->join(); $participant->setStatus((new \App\Entity\Common\Status())->setScope('ProjectParticipant')->setName('active')); }
        $repository = $this->createStub(ProjectParticipantRepository::class);
        $repository->method('findActiveParticipantsByProjectAndProfile')->willReturn($participants);
        $voter = new ProjectVoter($repository);
        $token = new UsernamePasswordToken($account, 'main', $account->getRoles());
        self::assertSame(1, $voter->vote($token, $project, [ProjectVoter::VIEW]));
        self::assertSame(1, $voter->vote($token, $project, [ProjectVoter::EDIT]));
        self::assertSame(-1, $voter->vote($token, $project, [ProjectVoter::DELETE]));
    }

    /*
     * Controleert dat de eigenaar van een project toegang heeft
     * tot zijn eigen project.
     */
    public function testOwnerCanViewOwnProject(): void
    {
        $repository = $this->createMock(ProjectParticipantRepository::class);

        $repository->expects(self::never())->method('findActiveParticipantsByProjectAndProfile');
        $voter = new ProjectVoter($repository);

        $account = new Account();

        $project = $this->createStub(Project::class);

        $project
            ->method('getOwnerAccount')
            ->willReturn($account);

        $token = new UsernamePasswordToken(
            $account,
            'main',
            $account->getRoles()
        );

        $result = $voter->vote(
            $token,
            $project,
            [ProjectVoter::VIEW]
        );

        $this->assertSame(1, $result);
    }

    /*
     * Controleert dat een gebruiker die geen eigenaar en geen
     * participant van het project is, geen toegang krijgt.
     */
    public function testNonParticipantCannotViewProject(): void
    {
        $repository = $this->createMock(ProjectParticipantRepository::class);

        $voter = new ProjectVoter($repository);

        $account = $this->createStub(Account::class);
        $profile = new Profile();

        $account
            ->method('getProfile')
            ->willReturn($profile);

        $project = $this->createStub(Project::class);

        $project
            ->method('getOwnerAccount')
            ->willReturn(null);

        $repository
            ->expects(self::once())
            ->method('findActiveParticipantsByProjectAndProfile')
            ->with($project, $profile)
            ->willReturn([]);

        $token = new UsernamePasswordToken(
            $account,
            'main',
            ['ROLE_USER']
        );

        $result = $voter->vote(
            $token,
            $project,
            [ProjectVoter::VIEW]
        );

        $this->assertSame(-1, $result);
    }

    /*
     * Controleert dat een participant zonder projectrol
     * geen toegang krijgt.
     */
    public function testParticipantWithoutRoleCannotViewProject(): void
    {
        $repository = $this->createMock(ProjectParticipantRepository::class);

        $voter = new ProjectVoter($repository);

        $account = $this->createStub(Account::class);
        $profile = new Profile();

        $account
            ->method('getProfile')
            ->willReturn($profile);

        $project = $this->createStub(Project::class);

        $project
            ->method('getOwnerAccount')
            ->willReturn(null);

        $participant = new ProjectParticipant();
        $participant->join();
        $participant->setStatus((new \App\Entity\Common\Status())->setScope('ProjectParticipant')->setName('active'));

        $repository
            ->expects(self::once())
            ->method('findActiveParticipantsByProjectAndProfile')
            ->with($project, $profile)
            ->willReturn([$participant]);

        $token = new UsernamePasswordToken(
            $account,
            'main',
            ['ROLE_USER']
        );

        $result = $voter->vote(
            $token,
            $project,
            [ProjectVoter::VIEW]
        );

        $this->assertSame(-1, $result);
    }

    /*
     * Controleert dat een actieve participant met VIEW-permission
     * toegang krijgt tot het specifieke project.
     */
    public function testParticipantWithRoleCanViewProject(): void
    {
        $repository = $this->createMock(ProjectParticipantRepository::class);

        $voter = new ProjectVoter($repository);

        $account = $this->createStub(Account::class);
        $profile = new Profile();

        $account
            ->method('getProfile')
            ->willReturn($profile);

        $project = $this->createStub(Project::class);

        $project
            ->method('getOwnerAccount')
            ->willReturn(null);

        /*
         * VIEW gebruikt bit 1 van de permissions mask.
         */
        $role = new ProjectRole();
        $role->setProject($project)->setPermissionsMask(1);

        $participant = new ProjectParticipant();
        $participant->join();
        $participant->setStatus((new \App\Entity\Common\Status())->setScope('ProjectParticipant')->setName('active'));
        $participant->setProject($project);
        $participant->setProfile($profile);
        $participant->setRole($role);

        $repository
            ->expects(self::once())
            ->method('findActiveParticipantsByProjectAndProfile')
            ->with($project, $profile)
            ->willReturn([$participant]);

        $token = new UsernamePasswordToken(
            $account,
            'main',
            ['ROLE_USER']
        );

        $result = $voter->vote(
            $token,
            $project,
            [ProjectVoter::VIEW]
        );

        $this->assertSame(1, $result);
    }
}
