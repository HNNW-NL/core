<?php

namespace App\Module\Main\Handler;

use App\Entity\Account\Account;
use App\Entity\Account\Profile;
use App\Entity\Common\Status;
use App\Entity\Log\AuditLog;
use App\Entity\Project\PackageTask;
use App\Entity\Project\Project;
use App\Enum\PackageTaskWorkflowStatus;
use App\Repository\Log\AuditLogRepository;
use App\Repository\Project\ProjectParticipantRepository;
use App\Security\Voter\ProjectVoter;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Uid\Ulid;

final readonly class PackageTaskWorkflowHandler
{
    private const MAX_SUBMISSION_LENGTH = 20000;
    private const MAX_REVIEW_REASON_LENGTH = 2000;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private AuditLogRepository $auditLogRepository,
        private ProjectParticipantRepository $participantRepository,
        private AuthorizationCheckerInterface $authorizationChecker,
        private RequestStack $requestStack,
    ) {
    }

    public function assign(
        PackageTask $task,
        Profile $profile,
        Account $actor,
        bool $allowReassignment = false,
    ): void
    {
        $this->entityManager->getConnection()->transactional(function () use ($task, $profile, $actor, $allowReassignment): void {
            $lockedTask = $this->findLockedTask((string) $task->getId(), $task->getWorkPackage()?->getProject());

            if ($lockedTask === null) {
                throw new \InvalidArgumentException('Task not found.');
            }

            $current = $this->currentStatus($lockedTask);
            if (!in_array($current, [PackageTaskWorkflowStatus::OPEN, PackageTaskWorkflowStatus::ASSIGNED], true)) {
                throw new \InvalidArgumentException('This task can no longer be assigned.');
            }
            if (!$this->participantRepository->isActiveParticipant($lockedTask->getWorkPackage()->getProject(), $profile)) {
                throw new \InvalidArgumentException('The assigned profile is not an active project participant.');
            }

            $oldProfileId = $lockedTask->getAssignedProfile()?->getId()->toBase32();
            if ($oldProfileId === $profile->getId()->toBase32()) {
                return;
            }
            if ($oldProfileId !== null && !$allowReassignment) {
                throw new \InvalidArgumentException('This task is already assigned to another participant.');
            }

            $status = $this->transitionStatus(PackageTaskWorkflowStatus::ASSIGNED);
            $lockedTask->setAssignedProfile($profile);
            $lockedTask->setStatus($status);

            $this->recordEvent($lockedTask, $actor, 'package_task.assigned', [
                'assigned_profile_id' => $profile->getId()->toBase32(),
                'status' => PackageTaskWorkflowStatus::ASSIGNED->value,
            ], [
                'assigned_profile_id' => $oldProfileId,
                'status' => $current->value,
            ]);

            $this->entityManager->flush();
        });
    }

    public function start(
        Project $project,
        string $taskId,
        Profile $profile,
        Account $actor,
    ): void {
        $this->entityManager->getConnection()->transactional(function () use ($project, $taskId, $profile, $actor): void {
            $task = $this->findLockedTask($taskId, $project);
            if ($task === null || !$this->isAssignedTo($task, $profile)) {
                throw new \InvalidArgumentException('Only the assigned participant can start this task.');
            }
            if (!$this->participantRepository->isActiveParticipant($project, $profile)) {
                throw new AccessDeniedException('An active project participant is required.');
            }

            $this->transition($task, PackageTaskWorkflowStatus::IN_PROGRESS, $actor, 'package_task.started');
        });
    }

    public function submit(
        Project $project,
        string $taskId,
        Profile $profile,
        Account $actor,
        string $submission,
    ): void {
        $submission = trim($submission);
        if ($submission === '' || mb_strlen($submission) > self::MAX_SUBMISSION_LENGTH) {
            throw new \InvalidArgumentException(sprintf(
                'The result must contain 1 to %d characters.',
                self::MAX_SUBMISSION_LENGTH,
            ));
        }

        $this->entityManager->getConnection()->transactional(function () use ($project, $taskId, $profile, $actor, $submission): void {
            $task = $this->findLockedTask($taskId, $project);
            if ($task === null || !$this->isAssignedTo($task, $profile)) {
                throw new \InvalidArgumentException('Only the assigned participant can submit a result.');
            }
            if (!$this->participantRepository->isActiveParticipant($project, $profile)) {
                throw new AccessDeniedException('An active project participant is required.');
            }

            $this->transition(
                $task,
                PackageTaskWorkflowStatus::SUBMITTED,
                $actor,
                'package_task.submitted',
                [
                    'submission' => $submission,
                    'review_reason' => null,
                    'reviewer_profile_id' => null,
                ],
            );
        });
    }

    public function review(
        Project $project,
        string $taskId,
        Account $actor,
        string $decision,
        string $reason = '',
    ): void {
        if (!$this->authorizationChecker->isGranted(ProjectVoter::EDIT, $project)) {
            throw new AccessDeniedException('Only project managers can review task results.');
        }

        $decision = strtolower(trim($decision));
        if (!in_array($decision, ['accept', 'return'], true)) {
            throw new \InvalidArgumentException('Unknown task review decision.');
        }

        $reason = trim($reason);
        if (($decision === 'return' && $reason === '') || mb_strlen($reason) > self::MAX_REVIEW_REASON_LENGTH) {
            throw new \InvalidArgumentException(sprintf(
                'A return reason is required and may not exceed %d characters.',
                self::MAX_REVIEW_REASON_LENGTH,
            ));
        }

        $this->entityManager->getConnection()->transactional(function () use ($project, $taskId, $actor, $decision, $reason): void {
            $task = $this->findLockedTask($taskId, $project);
            if ($task === null) {
                throw new \InvalidArgumentException('Task not found in this project.');
            }

            $target = $decision === 'accept'
                ? PackageTaskWorkflowStatus::ACCEPTED
                : PackageTaskWorkflowStatus::CHANGES_REQUESTED;
            $this->transition(
                $task,
                $target,
                $actor,
                $decision === 'accept' ? 'package_task.accepted' : 'package_task.returned',
                [
                    'review_reason' => $reason,
                    'reviewer_profile_id' => $actor->getProfile()?->getId()->toBase32(),
                ],
            );
        });
    }

    private function transition(
        PackageTask $task,
        PackageTaskWorkflowStatus $next,
        Account $actor,
        string $action,
        array $additionalValues = [],
    ): void {
        $current = $this->currentStatus($task);
        if (!$current->canTransitionTo($next)) {
            throw new \InvalidArgumentException(sprintf(
                'Cannot change task status from "%s" to "%s".',
                $current->value,
                $next->value,
            ));
        }

        $status = $this->transitionStatus($next);
        $task->setStatus($status);
        $latestEvent = $this->auditLogRepository->findLatestPackageTaskWorkflowEvents([
            $task->getId()->toBase32(),
        ])[ $task->getId()->toBase32()] ?? null;
        $lastValues = $latestEvent?->getNewValuesJson() ?? [];
        $submission = array_key_exists('submission', $additionalValues)
            ? $additionalValues['submission']
            : ($lastValues['submission'] ?? null);
        $reviewReason = array_key_exists('review_reason', $additionalValues)
            ? $additionalValues['review_reason']
            : ($lastValues['review_reason'] ?? null);
        $reviewerProfileId = array_key_exists('reviewer_profile_id', $additionalValues)
            ? $additionalValues['reviewer_profile_id']
            : ($lastValues['reviewer_profile_id'] ?? null);

        $this->recordEvent($task, $actor, $action, [
            'status' => $next->value,
            'submission' => $submission,
            'review_reason' => $reviewReason,
            'reviewer_profile_id' => $reviewerProfileId,
        ], ['status' => $current->value]);
        $this->entityManager->flush();
    }

    private function transitionStatus(PackageTaskWorkflowStatus $next): Status
    {
        $repository = $this->entityManager->getRepository(Status::class);
        $status = $repository->findOneBy([
            'name' => $next->value,
            'scope' => PackageTaskWorkflowStatus::STATUS_SCOPE,
        ]);

        if (!$status instanceof Status) {
            $status = (new Status())
                ->setName($next->value)
                ->setScope(PackageTaskWorkflowStatus::STATUS_SCOPE)
                ->setColourHex('#10B981');
            $this->entityManager->persist($status);
        }

        return $status;
    }

    private function currentStatus(PackageTask $task): PackageTaskWorkflowStatus
    {
        if (strcasecmp((string) $task->getStatus()?->getScope(), PackageTaskWorkflowStatus::STATUS_SCOPE) !== 0) {
            throw new \InvalidArgumentException('Task status is outside the task workflow scope.');
        }

        $status = PackageTaskWorkflowStatus::fromStoredName($task->getStatus()?->getName());
        if ($status === null) {
            throw new \InvalidArgumentException('Task has an unknown status.');
        }

        if ($status === PackageTaskWorkflowStatus::OPEN && $task->getAssignedProfile() !== null) {
            return PackageTaskWorkflowStatus::ASSIGNED;
        }

        return $status;
    }

    private function findLockedTask(string $taskId, ?Project $project): ?PackageTask
    {
        if ($project === null || !Ulid::isValid($taskId)) {
            return null;
        }

        $task = $this->entityManager->find(
            PackageTask::class,
            Ulid::fromString($taskId),
            LockMode::PESSIMISTIC_WRITE,
        );

        if (!$task instanceof PackageTask
            || $task->getDeletedAt() !== null
            || $task->getWorkPackage()?->getDeletedAt() !== null
            || $task->getWorkPackage()?->getProject() !== $project
        ) {
            return null;
        }

        return $task;
    }

    private function isAssignedTo(PackageTask $task, Profile $profile): bool
    {
        return $task->getAssignedProfile()?->getId()->equals($profile->getId()) ?? false;
    }

    private function recordEvent(
        PackageTask $task,
        Account $actor,
        string $action,
        array $newValues,
        array $oldValues,
    ): void {
        $request = $this->requestStack->getCurrentRequest();
        $auditLog = (new AuditLog())
            ->setActorAccount($actor)
            ->setActorUsername($actor->getUserIdentifier())
            ->setActorEmail($actor->getEmail() ?? '')
            ->setAction($action)
            ->setEntityType(PackageTask::class)
            ->setEntityId($task->getId()->toBase32())
            ->setOldValuesJson($oldValues)
            ->setNewValuesJson($newValues)
            ->setContextJson([
                'actor_profile_id' => $actor->getProfile()?->getId()->toBase32(),
            ])
            ->setRequestIp($request?->getClientIp() ?? 'unknown')
            ->setUserAgent($request?->headers->get('User-Agent') ?? 'unknown');

        $this->entityManager->persist($auditLog);
    }
}
