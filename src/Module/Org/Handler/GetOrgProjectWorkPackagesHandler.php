<?php

namespace App\Module\Org\Handler;

use App\Entity\Common\Status;
use App\Module\Main\Service\WorkPackageWorkflowService;
use App\Module\Org\Mapper\WorkPackageMapper;
use App\Module\Org\Service\WorkPackageOverviewService;
use App\Security\Voter\WorkPackageVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class GetOrgProjectWorkPackagesHandler
{
    public function __construct(
        private readonly WorkPackageOverviewService $workPackageOverviewService,
        private readonly WorkPackageMapper $workPackageMapper,
        private readonly WorkPackageWorkflowService $workflow,
        private readonly EntityManagerInterface $entityManager,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    public function handle(string $projectId): array
    {
        $packages = $this->workPackageOverviewService->getWorkPackagesForProjectId($projectId);
        $statusOptions = $packages === [] ? [] : $this->entityManager->getRepository(Status::class)
            ->findBy(['scope' => WorkPackageWorkflowService::STATUS_SCOPE]);
        $availableNames = array_map(static fn (Status $status): ?string => $status->getName(), $statusOptions);

        $workPackages = [];
        foreach ($packages as $package) {
            $status = $package->getStatus();
            $allowed = [];
            if ($status?->getScope() === WorkPackageWorkflowService::STATUS_SCOPE
                && $status->getName() !== null
                && $this->authorizationChecker->isGranted(WorkPackageVoter::EDIT, $package)
            ) {
                $allowed = array_intersect($this->workflow->getAllowedTransitions($status->getName()), $availableNames);
            }
            $workPackages[] = $this->workPackageMapper->toArray($package) + ['allowedStatuses' => $allowed];
        }

        $taskCount = array_sum(array_column($workPackages, 'taskCount'));
        $completedTaskCount = array_sum(array_column($workPackages, 'completedTaskCount'));

        return [
            'workPackages' => $workPackages,
            'workPackageCount' => count($workPackages),
            'taskCount' => $taskCount,
            'averageProgress' => $taskCount > 0 ? (int) round(($completedTaskCount / $taskCount) * 100) : 0,
        ];
    }
}
