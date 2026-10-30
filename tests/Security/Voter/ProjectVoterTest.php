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
    /*
     * Controleert dat de eigenaar van een project toegang heeft
     * tot zijn eigen project.
     */
    public function testOwnerCanViewOwnProject(): void
    {
        $repository = $this->createMock(ProjectParticipantRepository::class);

        $voter = new ProjectVoter($repository);

        $account = new Account();

        $project = $this->createMock(Project::class);

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

        $account = $this->createMock(Account::class);
        $profile = new Profile();

        $account
            ->method('getProfile')
            ->willReturn($profile);

        $project = $this->createMock(Project::class);

        $project
            ->method('getOwnerAccount')
            ->willReturn(null);

        $repository
            ->method('findActiveParticipant')
            ->with($project, $profile)
            ->willReturn(null);

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

        $account = $this->createMock(Account::class);
        $profile = new Profile();

        $account
            ->method('getProfile')
            ->willReturn($profile);

        $project = $this->createMock(Project::class);

        $project
            ->method('getOwnerAccount')
            ->willReturn(null);

        $participant = new ProjectParticipant();

        $repository
            ->method('findActiveParticipant')
            ->with($project, $profile)
            ->willReturn($participant);

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

        $account = $this->createMock(Account::class);
        $profile = new Profile();

        $account
            ->method('getProfile')
            ->willReturn($profile);

        $project = $this->createMock(Project::class);

        $project
            ->method('getOwnerAccount')
            ->willReturn(null);

        /*
         * VIEW gebruikt bit 1 van de permissions mask.
         */
        $role = new ProjectRole();
        $role->setPermissionsMask(1);

        $participant = new ProjectParticipant();
        $participant->setProject($project);
        $participant->setProfile($profile);
        $participant->setRole($role);

        $repository
            ->method('findActiveParticipant')
            ->with($project, $profile)
            ->willReturn($participant);

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