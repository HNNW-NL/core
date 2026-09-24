<?php

namespace App\Tests\Security\Voter;

use App\Entity\Account\Account;
use App\Entity\Account\Profile;
use App\Entity\Project\Project;
use App\Entity\Project\ProjectParticipant;
use App\Entity\Project\ProjectRole;
use App\Entity\Project\WorkPackage;
use App\Repository\Project\ProjectParticipantRepository;
use App\Security\Voter\WorkPackageVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class WorkPackageVoterTest extends TestCase
{
    /*
     * De eigenaar van het project mag een WorkPackage
     * binnen zijn eigen project bekijken.
     */
    public function testProjectOwnerCanViewWorkPackage(): void
    {
        $repository = $this->createMock(ProjectParticipantRepository::class);
        $voter = new WorkPackageVoter($repository);

        $account = new Account();

        $project = $this->createMock(Project::class);
        $project
            ->method('getOwnerAccount')
            ->willReturn($account);

        $workPackage = new WorkPackage();
        $workPackage->setProject($project);

        $token = new UsernamePasswordToken(
            $account,
            'main',
            $account->getRoles()
        );

        $result = $voter->vote(
            $token,
            $workPackage,
            [WorkPackageVoter::VIEW]
        );

        $this->assertSame(1, $result);
    }

    /*
     * Een gebruiker die geen participant is van het project
     * mag het WorkPackage niet bekijken.
     */
    public function testNonParticipantCannotViewWorkPackage(): void
    {
        $repository = $this->createMock(ProjectParticipantRepository::class);
        $voter = new WorkPackageVoter($repository);

        $account = $this->createMock(Account::class);
        $profile = new Profile();

        $account
            ->method('getProfile')
            ->willReturn($profile);

        $project = $this->createMock(Project::class);
        $project
            ->method('getOwnerAccount')
            ->willReturn(null);

        $workPackage = new WorkPackage();
        $workPackage->setProject($project);

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
            $workPackage,
            [WorkPackageVoter::VIEW]
        );

        $this->assertSame(-1, $result);
    }

    /*
     * Een participant zonder projectrol
     * krijgt geen toegang tot het WorkPackage.
     */
    public function testParticipantWithoutRoleCannotViewWorkPackage(): void
    {
        $repository = $this->createMock(ProjectParticipantRepository::class);
        $voter = new WorkPackageVoter($repository);

        $account = $this->createMock(Account::class);
        $profile = new Profile();

        $account
            ->method('getProfile')
            ->willReturn($profile);

        $project = $this->createMock(Project::class);
        $project
            ->method('getOwnerAccount')
            ->willReturn(null);

        $workPackage = new WorkPackage();
        $workPackage->setProject($project);

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
            $workPackage,
            [WorkPackageVoter::VIEW]
        );

        $this->assertSame(-1, $result);
    }

    /*
     * Een participant met VIEW permission
     * mag het WorkPackage bekijken.
     */
    public function testParticipantWithViewPermissionCanViewWorkPackage(): void
    {
        $repository = $this->createMock(ProjectParticipantRepository::class);
        $voter = new WorkPackageVoter($repository);

        $account = $this->createMock(Account::class);
        $profile = new Profile();

        $account
            ->method('getProfile')
            ->willReturn($profile);

        $project = $this->createMock(Project::class);
        $project
            ->method('getOwnerAccount')
            ->willReturn(null);

        $workPackage = new WorkPackage();
        $workPackage->setProject($project);

        $role = new ProjectRole();
        $role->setPermissionsMask(1);

        $participant = new ProjectParticipant();
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
            $workPackage,
            [WorkPackageVoter::VIEW]
        );

        $this->assertSame(1, $result);
    }

    /*
     * VIEW permission alleen geeft geen EDIT toegang.
     */
    public function testParticipantWithViewPermissionCannotEditWorkPackage(): void
    {
        $repository = $this->createMock(ProjectParticipantRepository::class);
        $voter = new WorkPackageVoter($repository);

        $account = $this->createMock(Account::class);
        $profile = new Profile();

        $account
            ->method('getProfile')
            ->willReturn($profile);

        $project = $this->createMock(Project::class);
        $project
            ->method('getOwnerAccount')
            ->willReturn(null);

        $workPackage = new WorkPackage();
        $workPackage->setProject($project);

        $role = new ProjectRole();
        $role->setPermissionsMask(1);

        $participant = new ProjectParticipant();
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
            $workPackage,
            [WorkPackageVoter::EDIT]
        );

        $this->assertSame(-1, $result);
    }

    /*
     * EDIT permission geeft toegang om het WorkPackage te wijzigen.
     */
    public function testParticipantWithEditPermissionCanEditWorkPackage(): void
    {
        $repository = $this->createMock(ProjectParticipantRepository::class);
        $voter = new WorkPackageVoter($repository);

        $account = $this->createMock(Account::class);
        $profile = new Profile();

        $account
            ->method('getProfile')
            ->willReturn($profile);

        $project = $this->createMock(Project::class);
        $project
            ->method('getOwnerAccount')
            ->willReturn(null);

        $workPackage = new WorkPackage();
        $workPackage->setProject($project);

        $role = new ProjectRole();
        $role->setPermissionsMask(2);

        $participant = new ProjectParticipant();
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
            $workPackage,
            [WorkPackageVoter::EDIT]
        );

        $this->assertSame(1, $result);
    }

    /*
     * DELETE permission geeft toegang om het WorkPackage te verwijderen.
     */
    public function testParticipantWithDeletePermissionCanDeleteWorkPackage(): void
    {
        $repository = $this->createMock(ProjectParticipantRepository::class);
        $voter = new WorkPackageVoter($repository);

        $account = $this->createMock(Account::class);
        $profile = new Profile();

        $account
            ->method('getProfile')
            ->willReturn($profile);

        $project = $this->createMock(Project::class);
        $project
            ->method('getOwnerAccount')
            ->willReturn(null);

        $workPackage = new WorkPackage();
        $workPackage->setProject($project);

        $role = new ProjectRole();
        $role->setPermissionsMask(4);

        $participant = new ProjectParticipant();
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
            $workPackage,
            [WorkPackageVoter::DELETE]
        );

        $this->assertSame(1, $result);
    }
}