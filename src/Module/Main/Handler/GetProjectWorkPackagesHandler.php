<?php

namespace App\Module\Main\Handler;

use App\Entity\Project\Project;
use App\Module\Main\Service\CurrentProfileProvider;
use App\Module\Org\Mapper\WorkPackageMapper;
use App\Repository\Project\ProjectParticipantRepository;
use App\Repository\Project\ProjectRepository;
use App\Repository\Project\WorkPackageRepository;
use App\Security\ProjectPermission;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class GetProjectWorkPackagesHandler
{
    public function __construct(
        private CurrentProfileProvider $currentProfileProvider,
        private ProjectParticipantRepository $participantRepository,
        private WorkPackageRepository $workPackageRepository,
        private WorkPackageMapper $mapper,
        private ProjectRepository $projectRepository,
    ) {
    }

    public function handle(Project|string $project): array
    {
        if (is_string($project)) {
            $project = $this->projectRepository->findOneVisibleBySlug($project);
        }
        if ($project === null || $project->getDeletedAt() !== null) {
            throw new NotFoundHttpException('Project niet gevonden.');
        }
        $profile = $this->currentProfileProvider->getProfile();
        $participant = $profile === null ? null : $this->participantRepository
            ->findLatestActiveParticipantByProjectAndProfile($project, $profile);

        if ($participant === null || !ProjectPermission::VIEW->in($participant->getRole()?->getPermissionsMask())) {
            throw new AccessDeniedException('Je hebt geen toegang tot de werkpakketten van dit project.');
        }

        return $this->mapper->manyToArray(
            $this->workPackageRepository->findActiveByProject($project),
        );
    }
}
