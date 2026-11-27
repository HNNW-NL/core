<?php


namespace App\Module\Org\Handler;

use App\Entity\Account\Profile;
use App\Entity\Common\Status;
use App\Entity\Project\Project;
use App\Entity\Project\ProjectApplication;
use App\Entity\Project\ProjectParticipant;
use App\Repository\Common\StatusRepository;
use App\Repository\Project\ProjectParticipantRepository;
use App\Repository\Project\ProjectRoleRepository;
use App\Workflow\ProjectApplicationWorkflow;
use App\Module\Org\Service\ProjectApplicationReviewService;
use Doctrine\ORM\EntityManagerInterface;

class ProjectParticipantUpdateRoleHandler
{
    // OR06-A: aanvragen beoordelen
    public const REJECT_REASON_MAX_LENGTH = ProjectApplicationWorkflow::REJECT_REASON_MAX_LENGTH;

    public const APPLICATION_SCOPE = ProjectApplicationWorkflow::APPLICATION_SCOPE;
    private const PARTICIPANT_SCOPE = 'ProjectParticipant';

    /** 'send' komt uit de bestaande fixtures; de rest zijn toegestane synoniemen. */
    public const PENDING_NAMES = ProjectApplicationWorkflow::PENDING_NAMES;
    public const ACCEPTED_NAMES = ProjectApplicationWorkflow::ACCEPTED_NAMES;
    public const REJECTED_NAMES = ProjectApplicationWorkflow::REJECTED_NAMES;
    private const PARTICIPANT_ACTIVE_NAMES = \App\Security\ProjectParticipation::ACTIVE_NAMES;

    public function __construct(
        private ProjectParticipantRepository $participantRepo,
        private ProjectRoleRepository $roleRepo,
        private EntityManagerInterface $em,
        private StatusRepository $statusRepo,
        private ?ProjectApplicationReviewService $reviews = null,
    ) {}

    /**
     * @param array<int, int> $roleMap [participantId => roleId]
     */
    public function handle(array $roleMap, $project): void
    {
        foreach ($roleMap as $participantId => $roleId) {

            $participant = $this->participantRepo->find($participantId);
            $role = $this->roleRepo->find($roleId);
            if (!$role) {
                continue;
            }
            if (!$participant) {
                continue;
            }
            if ($participant->getProject() !== $project) {
                continue;
            }
            if ($role->getProject() !== $project) {
                continue;
            }

            $participant->setRole($role);
        }

        $this->em->flush();
    }

    // ------------------------------------------------------------------
    // OR06-A: ingediende aanvragen accepteren / afwijzen
    // ------------------------------------------------------------------

    public function isPending(ProjectApplication $application): bool
    {
        $status = $application->getStatus();
        return $status !== null && $this->isPendingStatus($status);
    }

    public function isPendingStatus(Status $status): bool
    {
        return ProjectApplicationWorkflow::matches($status, self::PENDING_NAMES);
    }

    public function applicationStatusLabel(Status $status): string
    {
        $name = (string) $status->getName();
        if (strcasecmp((string) $status->getScope(), self::APPLICATION_SCOPE) !== 0) {
            return $name;
        }

        $code = mb_strtolower($name);

        return match (true) {
            in_array($code, self::PENDING_NAMES, true) => 'Ingediend',
            in_array($code, self::ACCEPTED_NAMES, true) => 'Geaccepteerd',
            in_array($code, self::REJECTED_NAMES, true) => 'Afgewezen',
            default => $name,
        };
    }

    public function configuredTransitions(Status $status): array
    {
        if (!$this->isPendingStatus($status)) {
            return [];
        }

        $targets = [];
        $targetNames = $status->allowsApplicationRejection() ? [self::REJECTED_NAMES] : [];
        if ($status->allowsApplicationAcceptance()
            && $this->statusRepo->findFirstByScopeAndNames(self::PARTICIPANT_SCOPE, self::PARTICIPANT_ACTIVE_NAMES) !== null) {
            array_unshift($targetNames, self::ACCEPTED_NAMES);
        }
        foreach ($targetNames as $names) {
            $target = $this->statusRepo->findFirstByScopeAndNames(self::APPLICATION_SCOPE, $names);
            if ($target !== null) {
                $targets[] = $this->applicationStatusLabel($target);
            }
        }

        return $targets;
    }

    public function acceptApplication(Project $project, string $applicationId, Profile $reviewer): array
    {
        return $this->reviewService()->acceptApplication($project, $applicationId, $reviewer);
    }

    public function rejectApplication(Project $project, string $applicationId, ?string $reason, Profile $reviewer): array
    {
        return $this->reviewService()->rejectApplication($project, $applicationId, $reason, $reviewer);
    }

    private function reviewService(): ProjectApplicationReviewService
    {
        return $this->reviews ?? new ProjectApplicationReviewService($this->participantRepo, $this->em, $this->statusRepo);
    }
}
