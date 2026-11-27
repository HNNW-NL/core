<?php

namespace App\Twig\Components\Org;

use App\Entity\Log\AuditLog;
use App\Module\Main\Handler\PackageTaskWorkflowHandler;
use App\Repository\Project\ProjectRepository;
use App\Repository\Log\AuditLogRepository;
use App\Repository\Project\PackageTaskRepository;
use App\Repository\Project\WorkPackageRepository;
use App\Repository\Account\ProfileRepository;
use App\Entity\Project\Project;
use App\Module\Org\Handler\ModifyTasksHandler;
use App\Security\CurrentAccountProvider;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\TwigComponent\Attribute\PostMount;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use App\Security\Voter\ProjectVoter;

#[AsLiveComponent]
class ModifyTasksComponent
{
    use DefaultActionTrait;

    #[LiveProp]
    public string $projectId = '';

    public string $errorMessage = '';

    #[LiveProp(writable: true)]
    public array $taskMap = [];

    #[LiveProp(writable: true)]
    public array $reviewData = [];

    public function __construct(readonly private AuthorizationCheckerInterface $authorizationChecker,
                                readonly private ProjectRepository             $projectRepository,
                                readonly private PackageTaskRepository         $packageTaskRepository,
                                readonly private WorkPackageRepository         $workPackageRepository,
                                readonly private ModifyTasksHandler            $modifyTasksHandler,
                                readonly private ProfileRepository $profileRepository,
                                readonly private CurrentAccountProvider $currentAccountProvider,
                                readonly private PackageTaskWorkflowHandler $taskWorkflowHandler,
                                readonly private AuditLogRepository $auditLogRepository,
                                readonly private CsrfTokenManagerInterface $csrfTokenManager,
    )
    {
    }

    public function getProject(): Project
    {
        return $this->projectRepository->find($this->projectId) ?? throw new NotFoundHttpException();
    }

    public function getTasks(): iterable
    {
        return $this->packageTaskRepository->findByProject($this->getProject());
    }

    public function getWorkPackages(): iterable
    {
        return $this->workPackageRepository->findActiveByProject($this->getProject());
    }

    public function getProfiles(): iterable
    {
        return $this->profileRepository->findByProject($this->getProject());
    }

    /** @return array<string, AuditLog> */
    public function getLatestWorkflowEvents(): array
    {
        $taskIds = array_map(
            static fn ($task): string => $task->getId()->toBase32(),
            $this->getTasks(),
        );

        return $this->auditLogRepository->findLatestPackageTaskWorkflowEvents($taskIds);
    }

    #[PostMount]
    public function postMount(): void
    {
        foreach ($this->getTasks() as $task) {
            $this->taskMap[(string) $task->getId()] = [
                'title' => $task->getTitle(),
                'assignedProfile' => (string) $task->getAssignedProfile()?->getId() ?? '',
                'description' => $task->getDescription(),
                'workPackage' => (string) $task->getWorkPackage()->getId(),
                'status' => (string) $task->getStatus()->getId(),
                'priority' => $task->getPriority(),
                'slug' => $task->getSlug(),
                'dueDate' => $task->getDueDate()?->format('Y-m-d\TH:i') ?? ''
            ];
        }
    }

    #[LiveAction]
    public function updateTask(#[LiveArg] string $id): void
    {
        if (!$this->authorizationChecker->isGranted('PROJECT_EDIT', $this->getProject())) {
            $this->errorMessage = 'You are not allowed to edit tasks for this project';
            return;
        }
        if (!isset($this->taskMap[$id])) {
            return;
        }

        $taskData = $this->taskMap[$id];
        $taskData['id'] = $id;
        $actor = $this->currentAccountProvider->getAccount();
        if ($actor === null) {
            $this->errorMessage = 'You must be logged in to edit project tasks.';
            return;
        }

        try {
            $this->modifyTasksHandler->handleModifyTask($taskData, $this->getProject(), $actor);
        } catch (\InvalidArgumentException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    #[LiveAction]
    public function deleteTask(#[LiveArg] string $id): void
    {
        if (!$this->authorizationChecker->isGranted('PROJECT_EDIT', $this->getProject())) {
            $this->errorMessage = 'You are not allowed to edit tasks for this project';
            return;
        }

        try {
            $this->modifyTasksHandler->deleteTaskById($id, $this->getProject());
        } catch (\InvalidArgumentException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    #[LiveAction]
    public function restoreTask(#[LiveArg] string $id): void
    {
        if (!$this->authorizationChecker->isGranted('PROJECT_EDIT', $this->getProject())) {
            $this->errorMessage = 'You are not allowed to edit tasks for this project';
            return;
        }

        try {
            $this->modifyTasksHandler->restoreTaskById($id, $this->getProject());
        } catch (\InvalidArgumentException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    #[LiveAction]
    public function reviewTask(
        #[LiveArg] string $id,
        #[LiveArg] string $decision,
        #[LiveArg] string $token,
    ): void {
        $project = $this->getProject();
        if (!$this->authorizationChecker->isGranted(ProjectVoter::EDIT, $project)) {
            $this->errorMessage = 'You are not allowed to review task results for this project';
            return;
        }

        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('review-package-task-' . $id, $token))) {
            $this->errorMessage = 'Invalid security token. Refresh the page and try again.';
            return;
        }

        $actor = $this->currentAccountProvider->getAccount();
        if ($actor === null) {
            $this->errorMessage = 'You must be logged in to review task results.';
            return;
        }

        $reason = $this->reviewData[$id]['reason'] ?? '';
        if (!is_string($reason)) {
            $reason = '';
        }

        try {
            $this->taskWorkflowHandler->review($project, $id, $actor, $decision, $reason);
        } catch (\InvalidArgumentException $exception) {
            $this->errorMessage = $exception->getMessage();
        }
    }

}
