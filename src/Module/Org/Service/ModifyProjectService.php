<?php

namespace App\Module\Org\Service;

use App\Entity\Account\Account;
use App\Entity\Org\Organisation;
use App\Entity\Common\Status;
use App\Entity\Project\Project;
use App\Module\Org\DTO\ModifyProjectDTO;
use App\Module\Org\Handler\ModifyProjectHandler;
use App\Module\Org\Mapper\ModifyProjectMapper;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Symfony\Component\HttpFoundation\Request;

class ModifyProjectService
{
    private const TITLE_MAX_LENGTH = 120;
    private const SUMMARY_MIN_LENGTH = 5;
    private const SUMMARY_MAX_LENGTH = 280;
    private const DESCRIPTION_MIN_LENGTH = 10;
    private const DESCRIPTION_MAX_LENGTH = 500;
    private const CAPACITY_MIN = 1;
    private const CAPACITY_MAX = 500;
    private const ALLOWED_VISIBILITY = ['public', 'private', 'unlisted'];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ModifyProjectMapper $mapper,
    ) {}

    public function modify(ModifyProjectDTO $dto): Project
    {
        $project = $this->findProject($dto->projectId);

        if ($project === null) {
            throw new \RuntimeException('Project not found');
        }

        $this->assertProjectBelongsToOrganisation($project, $dto->organisationId);

        if ($dto->hasStatusNameChanged()) {
            $project->setStatus($this->resolveStatus($dto->statusName));
        }

        $this->validateModifyConstraints($dto, $project);

        $project = $this->mapper->toEntity($project, $dto);

        $this->applyPublicationState($dto, $project);

        $this->entityManager->flush();

        return $project;
    }

    public function buildResponsePayload(
        string $id,
        Request $request,
        ModifyProjectHandler $handler,
        ?Account $publisher = null
    ): array
    {
        $organisationId = $this->getRequestValue($request, 'organisation_id');
        $intent = $this->getRequestValue($request, 'intent');

        $projectRepository = $this->entityManager->getRepository(Project::class);
        $project = $this->resolveProjectReference($projectRepository, $id);

        $resolvedProjectId = $project instanceof Project ? (string) $project->getId() : $id;
        $resolvedProjectRef = $project instanceof Project ? $this->projectRef($project) : $id;

        if ($intent !== '') {
            return ['redirect' => $this->handleIntent(
                $intent,
                $request,
                $handler,
                $organisationId,
                $resolvedProjectId,
                $resolvedProjectRef,
                $project,
                $publisher
            )];
        }

        $projects = $this->loadOrganisationProjects($organisationId);

        if (!$project instanceof Project && !empty($projects)) {
            $project = $projects[0];
        }

        $currentRef = $project instanceof Project ? $this->projectRef($project) : $id;

        if ($project instanceof Project && $id !== $currentRef) {
            return ['redirect' => [
                'id' => $currentRef,
                'organisation_id' => $organisationId,
            ]];
        }

        return ['view' => [
            'id' => $currentRef,
            'organisationId' => $organisationId,
            'project' => $project instanceof Project ? $this->mapProject($project, $organisationId) : null,
            'projects' => $this->mapProjects($projects),
        ]];
    }

    public function delete(string $projectId, string $organisationId): Project
    {
        $project = $this->findProject($projectId);

        if ($project === null) {
            throw new \RuntimeException('Project not found');
        }

        if ($project->getOwnerOrganisation() === null) {
            throw new \RuntimeException('Project does not belong to an organisation');
        }

        $projectOrganisationId = (string) $project->getOwnerOrganisation()->getId();
        if ($projectOrganisationId !== $organisationId) {
            throw new \RuntimeException('Project does not belong to the selected organisation');
        }

        $this->entityManager->remove($project);
        $this->entityManager->flush();

        return $project;
    }

    private function handleIntent(
        string $intent,
        Request $request,
        ModifyProjectHandler $handler,
        string $organisationId,
        string $resolvedProjectId,
        string $resolvedProjectRef,
        ?Project $project = null,
        ?Account $publisher = null
    ): array {
        $actor = $publisher instanceof Account ? $publisher : new Account();

        if ($intent === 'delete') {
            try {
                $this->delete($resolvedProjectId, $organisationId);

                return [
                    'id' => $resolvedProjectRef,
                    'organisation_id' => $organisationId,
                    'status' => 'success',
                    'message' => 'Project deleted successfully.',
                ];
            } catch (\RuntimeException $e) {
                return [
                    'id' => $resolvedProjectRef,
                    'organisation_id' => $organisationId,
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];
            }
        }

        $request->request->set('project_id', $resolvedProjectId);
        $request->query->set('project_id', $resolvedProjectId);

        if ($project instanceof Project) {
            $request->attributes->set('_current_project', $project);
        }

        $result = $handler->handle($request, $actor);

        return [
            'id' => $resolvedProjectRef,
            'organisation_id' => $organisationId,
            'status' => !empty($result['success']) ? 'success' : 'error',
            'message' => !empty($result['success'])
                ? 'Project updated successfully.'
                : ($result['error'] ?? 'Update failed.'),
        ];
    }

    private function getRequestValue(Request $request, string $key): string
    {
        return trim((string) ($request->request->get($key) ?? $request->query->get($key) ?? ''));
    }

    private function resolveProjectReference(ObjectRepository $projectRepository, string $reference): ?Project
    {
        $reference = trim($reference);
        if ($reference === '') {
            return null;
        }

        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $reference)) {
            $project = $projectRepository->find($reference);
            return $project instanceof Project ? $project : null;
        }

        $project = $projectRepository->findOneBy(['slug' => $reference]);
        return $project instanceof Project ? $project : null;
    }

    private function projectRef(Project $project): string
    {
        return (string) ($project->getSlug() ?: $project->getId());
    }

    private function loadOrganisationProjects(string $organisationId): array
    {
        if ($organisationId === '') {
            return [];
        }

        $organisation = $this->entityManager->getRepository(Organisation::class)->find($organisationId);
        if (!$organisation instanceof Organisation) {
            return [];
        }

        return $this->entityManager->getRepository(Project::class)->findBy(
            ['ownerOrganisation' => $organisation],
            ['createdAt' => 'DESC']
        );
    }

    private function mapProject(Project $project, string $organisationId): array
    {
        return [
            'id' => (string) $project->getId(),
            'title' => $project->getTitle(),
            'name' => $project->getTitle(),
            'summary' => $project->getSummary(),
            'description' => $project->getDescription(),
            'visibility' => $project->getVisibility(),
            'ref' => $this->projectRef($project),
            'statusName' => $project->getStatus()?->getName(),
            'organisationId' => $project->getOwnerOrganisation() ? (string) $project->getOwnerOrganisation()->getId() : $organisationId,
            'startDate' => $project->getStartDate()?->format('Y-m-d'),
            'endDate' => $project->getEndDate()?->format('Y-m-d'),
            'capacity' => $project->getCapacity(),
        ];
    }

    private function mapProjects(array $projects): array
    {
        return array_map(function (Project $item): array {
            return [
                'id' => (string) $item->getId(),
                'ref' => $this->projectRef($item),
                'title' => $item->getTitle(),
                'name' => $item->getTitle(),
                'slug' => $item->getSlug(),
                'summary' => $item->getSummary(),
                'description' => $item->getDescription(),
                'visibility' => $item->getVisibility(),
                'statusName' => $item->getStatus()?->getName(),
                'organisationId' => $item->getOwnerOrganisation() ? (string) $item->getOwnerOrganisation()->getId() : null,
                'startDate' => $item->getStartDate()?->format('Y-m-d'),
                'endDate' => $item->getEndDate()?->format('Y-m-d'),
                'capacity' => $item->getCapacity(),
            ];
        }, $projects);
    }

    private function assertProjectBelongsToOrganisation(Project $project, string $organisationId): void
    {
        if ($project->getOwnerOrganisation() === null) {
            throw new \RuntimeException('Project does not belong to an organisation');
        }

        $projectOrganisationId = (string) $project->getOwnerOrganisation()->getId();
        if ($projectOrganisationId !== $organisationId) {
            throw new \RuntimeException('Project does not belong to the selected organisation');
        }
    }

    private function validateModifyConstraints(ModifyProjectDTO $dto, Project $project): void
    {
        $this->validateDateRange($dto, $project);
        $this->validateTextFields($dto);
        $this->validateCapacity($dto);
        $this->normalizeAndValidateVisibility($dto);
    }

    private function validateDateRange(ModifyProjectDTO $dto, Project $project): void
    {
        if ($dto->hasStartDateChanged() && $dto->startDate === null) {
            throw new \InvalidArgumentException('Start date cannot be empty.');
        }

        $effectiveStartDate = $dto->hasStartDateChanged() ? $dto->startDate : $project->getStartDate();
        $effectiveEndDate = $dto->hasEndDateChanged() ? $dto->endDate : $project->getEndDate();

        if ($effectiveEndDate !== null && $effectiveStartDate !== null && $effectiveEndDate < $effectiveStartDate) {
            throw new \InvalidArgumentException('End date cannot be earlier than start date.');
        }
    }

    private function validateTextFields(ModifyProjectDTO $dto): void
    {
        if ($dto->hasTitleChanged()) {
            $titleLength = $this->getCharacterLength(trim((string) ($dto->title ?? '')));
            if ($titleLength === 0) {
                throw new \InvalidArgumentException('Project title cannot be empty.');
            }

            if ($titleLength > self::TITLE_MAX_LENGTH) {
                throw new \InvalidArgumentException('Project title cannot exceed ' . self::TITLE_MAX_LENGTH . ' characters.');
            }
        }

        if ($dto->hasSummaryChanged()) {
            $summaryLength = $this->getCharacterLength(trim((string) ($dto->summary ?? '')));
            if ($summaryLength < self::SUMMARY_MIN_LENGTH || $summaryLength > self::SUMMARY_MAX_LENGTH) {
                throw new \InvalidArgumentException(
                    'Summary must be between ' . self::SUMMARY_MIN_LENGTH . ' and ' . self::SUMMARY_MAX_LENGTH . ' characters.'
                );
            }
        }

        if ($dto->hasDescriptionChanged()) {
            $descriptionLength = $this->getCharacterLength(trim((string) ($dto->description ?? '')));
            if ($descriptionLength < self::DESCRIPTION_MIN_LENGTH || $descriptionLength > self::DESCRIPTION_MAX_LENGTH) {
                throw new \InvalidArgumentException(
                    'Description must be between ' . self::DESCRIPTION_MIN_LENGTH . ' and ' . self::DESCRIPTION_MAX_LENGTH . ' characters.'
                );
            }
        }
    }

    private function validateCapacity(ModifyProjectDTO $dto): void
    {
        if ($dto->hasCapacityChanged() && $dto->capacity !== null) {
            if ($dto->capacity < self::CAPACITY_MIN || $dto->capacity > self::CAPACITY_MAX) {
                throw new \InvalidArgumentException(
                    'Capacity must be between ' . self::CAPACITY_MIN . ' and ' . self::CAPACITY_MAX . '.'
                );
            }
        }
    }

    private function normalizeAndValidateVisibility(ModifyProjectDTO $dto): void
    {
        if (!$dto->hasVisibilityChanged()) {
            return;
        }

        $visibility = strtolower(trim((string) ($dto->visibility ?? '')));
        if (!in_array($visibility, self::ALLOWED_VISIBILITY, true)) {
            throw new \InvalidArgumentException('Invalid visibility option.');
        }

        $dto->visibility = $visibility;
    }

    private function applyPublicationState(ModifyProjectDTO $dto, Project $project): void
    {
        if (!$dto->hasStatusNameChanged()) {
            return;
        }

        $statusName = $dto->statusName ?? '';
        if ($statusName === 'published' && $project->getPublishedAt() === null) {
            $project->publish();
        }

        if ($statusName === 'draft' || $statusName === 'archived') {
            $project->unpublish();
        }
    }

    private function getCharacterLength(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value, 'UTF-8');
        }

        return strlen($value);
    }

    private function findProject(string $projectId): ?Project
    {
        $projectId = trim($projectId);
        if ($projectId === '') {
            return null;
        }

        $projectRepository = $this->entityManager->getRepository(Project::class);

        $project = null;
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $projectId)) {
            $project = $projectRepository->find($projectId);
        }

        if (!$project instanceof Project) {
            $project = $projectRepository->findOneBy(['slug' => $projectId]);
        }

        return $project instanceof Project ? $project : null;
    }

    private function resolveStatus(?string $statusName): Status
    {
        $statusName = trim((string) $statusName);
        if ($statusName === '') {
            throw new \InvalidArgumentException('Status is required');
        }

        $status = $this->entityManager->getRepository(Status::class)->findOneBy([
            'name' => $statusName,
            'scope' => 'project',
        ]);

        if (!$status instanceof Status) {
            throw new \RuntimeException('Unknown project status: ' . $statusName);
        }

        return $status;
    }
}
