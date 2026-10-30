<?php

namespace App\Tests\Security\Voter;

use App\Entity\Account\Account;
use App\Entity\Account\Profile;
use App\Entity\Project\PackageTask;
use App\Entity\Project\Project;
use App\Entity\Project\ProjectParticipant;
use App\Entity\Project\ProjectRole;
use App\Entity\Project\WorkPackage;
use App\Repository\Project\ProjectParticipantRepository;
use App\Security\Voter\PackageTaskVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class PackageTaskVoterTest extends TestCase
{
    public function testProjectOwnerCanViewTask(): void
    {
        $repository = $this->createMock(ProjectParticipantRepository::class);
        $voter = new PackageTaskVoter($repository);

        $account = new Account();

        $project = $this->createMock(Project::class);
        $project
            ->method('getOwnerAccount')
            ->willReturn($account);

        $workPackage = new WorkPackage();
        $workPackage->setProject($project);

        $task = new PackageTask();
        $task->setWorkPackage($workPackage);

        $token = new UsernamePasswordToken(
            $account,
            'main',
            $account->getRoles()
        );

        $result = $voter->vote(
            $token,
            $task,
            [PackageTaskVoter::VIEW]
        );

        $this->assertSame(1, $result);
    }

    public function testNonParticipantCannotViewTask(): void
    {
        $repository = $this->createMock(ProjectParticipantRepository::class);
        $voter = new PackageTaskVoter($repository);

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

        $task = new PackageTask();
        $task->setWorkPackage($workPackage);

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
            $task,
            [PackageTaskVoter::VIEW]
        );

        $this->assertSame(-1, $result);
    }

    public function testParticipantWithoutRoleCannotViewTask(): void
    {
        $repository = $this->createMock(ProjectParticipantRepository::class);
        $voter = new PackageTaskVoter($repository);

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

        $task = new PackageTask();
        $task->setWorkPackage($workPackage);

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
            $task,
            [PackageTaskVoter::VIEW]
        );

        $this->assertSame(-1, $result);
    }

    public function testParticipantWithViewPermissionCanViewTask(): void
    {
        $repository = $this->createMock(ProjectParticipantRepository::class);
        $voter = new PackageTaskVoter($repository);

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

        $task = new PackageTask();
        $task->setWorkPackage($workPackage);

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
            $task,
            [PackageTaskVoter::VIEW]
        );

        $this->assertSame(1, $result);
    }

    public function testParticipantWithViewPermissionCannotEditTask(): void
    {
        $repository = $this->createMock(ProjectParticipantRepository::class);
        $voter = new PackageTaskVoter($repository);

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

        $task = new PackageTask();
        $task->setWorkPackage($workPackage);

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
            $task,
            [PackageTaskVoter::EDIT]
        );

        $this->assertSame(-1, $result);
    }

    public function testParticipantWithEditPermissionCanEditTask(): void
    {
        $repository = $this->createMock(ProjectParticipantRepository::class);
        $voter = new PackageTaskVoter($repository);

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

        $task = new PackageTask();
        $task->setWorkPackage($workPackage);

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
            $task,
            [PackageTaskVoter::EDIT]
        );

        $this->assertSame(1, $result);
    }

    public function testParticipantWithDeletePermissionCanDeleteTask(): void
    {
        $repository = $this->createMock(ProjectParticipantRepository::class);
        $voter = new PackageTaskVoter($repository);

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

        $task = new PackageTask();
        $task->setWorkPackage($workPackage);

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
            $task,
            [PackageTaskVoter::DELETE]
        );

        $this->assertSame(1, $result);
    }
}