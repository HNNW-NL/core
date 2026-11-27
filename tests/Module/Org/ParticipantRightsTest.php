<?php

namespace App\Tests\Module\Org;

use App\Entity\Account\Profile;
use App\Entity\Project\Project;
use App\Entity\Project\ProjectParticipant;
use App\Entity\Project\ProjectRole;
use App\Module\Org\Handler\ProjectParticipantUpdateRoleHandler;
use App\Repository\Common\StatusRepository;
use App\Repository\Project\PackageTaskRepository;
use App\Repository\Project\ProjectParticipantRepository;
use App\Repository\Project\ProjectRoleRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * OR06-D: gerichte tests voor rechten en fouten van OR06-C
 * (deelname beëindigen, historische relaties, rolwijziging).
 */
final class ParticipantRightsTest extends TestCase
{
    private Project $project;
    private Profile $profile;

    protected function setUp(): void
    {
        $this->project = new Project();
        $this->profile = new Profile();
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function activeParticipant(?Project $project = null): ProjectParticipant
    {
        $participant = (new ProjectParticipant())
            ->setProject($project ?? $this->project)
            ->setProfile($this->profile);
        $participant->join();

        return $participant;
    }

    /**
     * @param ProjectParticipantRepository&\PHPUnit\Framework\MockObject\MockObject $participants
     */
    private function handler(
        ProjectParticipantRepository $participants,
        ?PackageTaskRepository $tasks = null,
        ?ProjectRoleRepository $roles = null,
        ?EntityManagerInterface $em = null,
    ): ProjectParticipantUpdateRoleHandler {
        $em ??= $this->transactionalEntityManager();

        return new ProjectParticipantUpdateRoleHandler(
            $participants,
            $roles ?? $this->createStub(ProjectRoleRepository::class),
            $em,
            $this->createStub(StatusRepository::class),
            $tasks ?? $this->createStub(PackageTaskRepository::class),
        );
    }

    private function transactionalEntityManager(): EntityManagerInterface
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('wrapInTransaction')->willReturnCallback(
            static fn (callable $operation): mixed => $operation(),
        );

        return $em;
    }

    // ------------------------------------------------------------------
    // Leeg / ongeldig bewijs: terminateParticipation
    // ------------------------------------------------------------------

    /** @return iterable<string, array{string}> */
    public static function invalidParticipantIds(): iterable
    {
        yield 'leeg' => [''];
        yield 'spaties' => ['   '];
        yield 'geen ulid' => ['niet-een-ulid'];
        yield 'te kort' => ['01ARZ3NDEKTSV4RRFFQ69G5FA'];
        yield 'ongeldig teken' => ['01ARZ3NDEKTSV4RRFFQ69G5FAU'];
        yield 'sql-injectie' => ["' OR 1=1 --"];
    }

    #[DataProvider('invalidParticipantIds')]
    public function testInvalidParticipantIdIsRejectedBeforeAnyDatabaseWork(string $participantId): void
    {
        $participants = $this->createMock(ProjectParticipantRepository::class);
        $participants->expects(self::never())->method('find');
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('wrapInTransaction');
        $em->expects(self::never())->method('lock');
        $tasks = $this->createMock(PackageTaskRepository::class);
        $tasks->expects(self::never())->method('hasOpenAssignedTasks');

        $result = $this->handler($participants, $tasks, null, $em)
            ->terminateParticipation($this->project, $participantId);

        self::assertFalse($result['success']);
        self::assertSame('Ongeldige deelnemer.', $result['message']);
    }

    public function testUnknownParticipantIsNotFound(): void
    {
        $participants = $this->createMock(ProjectParticipantRepository::class);
        $participants->expects(self::once())->method('find')->willReturn(null);

        $result = $this->handler($participants)
            ->terminateParticipation($this->project, (string) (new ProjectParticipant())->getId());

        self::assertFalse($result['success']);
        self::assertSame('Deelnemer niet gevonden.', $result['message']);
    }

    // ------------------------------------------------------------------
    // Onbevoegd: deelnemer van een ander project
    // ------------------------------------------------------------------

    public function testParticipantOfAnotherProjectCannotBeTerminated(): void
    {
        $foreignParticipant = $this->activeParticipant(new Project());
        $participants = $this->createStub(ProjectParticipantRepository::class);
        $participants->method('find')->willReturn($foreignParticipant);
        $tasks = $this->createMock(PackageTaskRepository::class);
        $tasks->expects(self::never())->method('hasOpenAssignedTasks');

        $result = $this->handler($participants, $tasks)
            ->terminateParticipation($this->project, (string) $foreignParticipant->getId());

        self::assertFalse($result['success']);
        self::assertSame('Deelnemer niet gevonden.', $result['message']);
        self::assertNull($foreignParticipant->getLeftAt());
    }

    // ------------------------------------------------------------------
    // Ongeldige toestand van de deelnemer
    // ------------------------------------------------------------------

    public function testAlreadyLeftParticipantCannotBeTerminatedAgain(): void
    {
        $participant = $this->activeParticipant();
        $participant->leave();
        $leftAt = $participant->getLeftAt();
        $participants = $this->createStub(ProjectParticipantRepository::class);
        $participants->method('find')->willReturn($participant);

        $result = $this->handler($participants)
            ->terminateParticipation($this->project, (string) $participant->getId());

        self::assertFalse($result['success']);
        self::assertSame('Deze deelnemer is niet actief.', $result['message']);
        self::assertSame($leftAt, $participant->getLeftAt(), 'leftAt mag niet overschreven worden.');
    }

    public function testParticipantThatNeverJoinedCannotBeTerminated(): void
    {
        $participant = (new ProjectParticipant())->setProject($this->project)->setProfile($this->profile);
        $participants = $this->createStub(ProjectParticipantRepository::class);
        $participants->method('find')->willReturn($participant);

        $result = $this->handler($participants)
            ->terminateParticipation($this->project, (string) $participant->getId());

        self::assertFalse($result['success']);
        self::assertNull($participant->getLeftAt());
    }

    public function testParticipantWithoutProfileCannotBeTerminated(): void
    {
        $participant = (new ProjectParticipant())->setProject($this->project);
        $participant->join();
        $participants = $this->createStub(ProjectParticipantRepository::class);
        $participants->method('find')->willReturn($participant);
        $tasks = $this->createMock(PackageTaskRepository::class);
        $tasks->expects(self::never())->method('hasOpenAssignedTasks');

        $result = $this->handler($participants, $tasks)
            ->terminateParticipation($this->project, (string) $participant->getId());

        self::assertFalse($result['success']);
        self::assertSame('Het profiel van de deelnemer ontbreekt.', $result['message']);
        self::assertNull($participant->getLeftAt());
    }

    // ------------------------------------------------------------------
    // Open toegewezen taken blokkeren het beëindigen
    // ------------------------------------------------------------------

    public function testTerminationIsBlockedWhileOpenAssignedTasksExist(): void
    {
        $participant = $this->activeParticipant();
        $participants = $this->createStub(ProjectParticipantRepository::class);
        $participants->method('find')->willReturn($participant);
        $tasks = $this->createMock(PackageTaskRepository::class);
        $tasks->expects(self::once())->method('hasOpenAssignedTasks')
            ->with($this->project, $this->profile)->willReturn(true);

        $result = $this->handler($participants, $tasks)
            ->terminateParticipation($this->project, (string) $participant->getId());

        self::assertFalse($result['success']);
        self::assertStringContainsString('Verdeel eerst de openstaande taken opnieuw', $result['message']);
        self::assertNull($participant->getLeftAt(), 'Deelname mag niet beëindigd zijn.');
    }

    public function testTerminationSucceedsAfterTasksHaveBeenRedistributed(): void
    {
        $participant = $this->activeParticipant();
        $participants = $this->createStub(ProjectParticipantRepository::class);
        $participants->method('find')->willReturn($participant);
        $tasks = $this->createStub(PackageTaskRepository::class);
        // Eerste poging: nog open taken. Na herverdeling: geen open taken meer.
        $tasks->method('hasOpenAssignedTasks')->willReturnOnConsecutiveCalls(true, false);
        $workflow = $this->handler($participants, $tasks);
        $id = (string) $participant->getId();

        self::assertFalse($workflow->terminateParticipation($this->project, $id)['success']);
        self::assertNull($participant->getLeftAt());

        self::assertTrue($workflow->terminateParticipation($this->project, $id)['success']);
        self::assertNotNull($participant->getLeftAt());
    }

    // ------------------------------------------------------------------
    // Historische relaties blijven behouden
    // ------------------------------------------------------------------

    public function testTerminationKeepsHistoricalRelations(): void
    {
        $role = (new ProjectRole())->setProject($this->project);
        $participant = $this->activeParticipant()->setRole($role);
        $joinedAt = $participant->getJoinedAt();
        $participants = $this->createStub(ProjectParticipantRepository::class);
        $participants->method('find')->willReturn($participant);
        $tasks = $this->createStub(PackageTaskRepository::class);
        $tasks->method('hasOpenAssignedTasks')->willReturn(false);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('wrapInTransaction')->willReturnCallback(static fn (callable $o): mixed => $o());
        $em->expects(self::never())->method('remove');
        $em->expects(self::once())->method('lock')->with($this->project, LockMode::PESSIMISTIC_WRITE);

        $result = $this->handler($participants, $tasks, null, $em)
            ->terminateParticipation($this->project, (string) $participant->getId());

        self::assertTrue($result['success']);
        self::assertNotNull($participant->getLeftAt());
        self::assertSame($joinedAt, $participant->getJoinedAt());
        self::assertSame($this->project, $participant->getProject());
        self::assertSame($this->profile, $participant->getProfile());
        self::assertSame($role, $participant->getRole());
    }

    // ------------------------------------------------------------------
    // Rolwijziging (handle): alleen actieve deelnemers van dit project
    // ------------------------------------------------------------------

    public function testRoleChangeIsAppliedToActiveParticipantsOnly(): void
    {
        $oldRole = (new ProjectRole())->setProject($this->project);
        $newRole = (new ProjectRole())->setProject($this->project);

        $active = $this->activeParticipant()->setRole($oldRole);
        $left = $this->activeParticipant()->setRole($oldRole);
        $left->leave();
        $neverJoined = (new ProjectParticipant())->setProject($this->project)->setRole($oldRole);

        $participants = $this->createStub(ProjectParticipantRepository::class);
        $participants->method('find')->willReturnCallback(
            static fn (mixed $id): ?ProjectParticipant => ['active' => $active, 'left' => $left, 'never' => $neverJoined][$id] ?? null,
        );
        $roles = $this->createStub(ProjectRoleRepository::class);
        $roles->method('find')->willReturn($newRole);

        $this->handler($participants, null, $roles)->handle(
            ['active' => 'r', 'left' => 'r', 'never' => 'r'],
            $this->project,
        );

        self::assertSame($newRole, $active->getRole());
        self::assertSame($oldRole, $left->getRole(), 'Historische deelnemer behoudt de oude rol.');
        self::assertSame($oldRole, $neverJoined->getRole());
    }

    public function testRoleChangeIgnoresForeignProjectParticipantAndForeignRole(): void
    {
        $otherProject = new Project();
        $ownRole = (new ProjectRole())->setProject($this->project);
        $foreignRole = (new ProjectRole())->setProject($otherProject);

        $foreignParticipant = $this->activeParticipant($otherProject)->setRole($foreignRole);
        $ownParticipant = $this->activeParticipant()->setRole($ownRole);

        $participants = $this->createStub(ProjectParticipantRepository::class);
        $participants->method('find')->willReturnCallback(
            static fn (mixed $id): ?ProjectParticipant => [
                'foreign-participant' => $foreignParticipant,
                'own-participant' => $ownParticipant,
            ][$id] ?? null,
        );
        $roles = $this->createStub(ProjectRoleRepository::class);
        $roles->method('find')->willReturnCallback(
            static fn (mixed $id): ?ProjectRole => ['own-role' => $ownRole, 'foreign-role' => $foreignRole][$id] ?? null,
        );

        $this->handler($participants, null, $roles)->handle([
            'foreign-participant' => 'own-role',   // deelnemer van ander project
            'own-participant' => 'foreign-role',   // rol van ander project
        ], $this->project);

        self::assertSame($foreignRole, $foreignParticipant->getRole());
        self::assertSame($ownRole, $ownParticipant->getRole());
    }

    public function testRoleChangeWithEmptyMapOrUnknownIdsChangesNothing(): void
    {
        $participants = $this->createStub(ProjectParticipantRepository::class);
        $participants->method('find')->willReturn(null);
        $roles = $this->createStub(ProjectRoleRepository::class);
        $roles->method('find')->willReturn(null);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('wrapInTransaction')->willReturnCallback(static fn (callable $o): mixed => $o());
        $em->expects(self::never())->method('refresh');

        $workflow = $this->handler($participants, null, $roles, $em);

        $workflow->handle([], $this->project);
        $workflow->handle(['onbekend' => 'onbekend'], $this->project);

        $this->addToAssertionCount(1);
    }
}
