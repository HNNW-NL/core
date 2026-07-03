<?php

namespace App\Module\Org\Service;

use App\Entity\Account\Account;
use App\Entity\Common\Status;
use App\Entity\Project\Project;
use App\Module\Org\DTO\ModifyProjectDTO;
use App\Module\Org\Handler\ModifyProjectHandler;
use App\Module\Org\Mapper\ModifyProjectMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ModifyProjectService
{
    private const ORGANISATION_QUERY_KEY = 'organisation_id';
    private const DATE_FORMAT = 'Y-m-d';
    private const DATE_TIME_FORMAT = 'Y-m-d H:i:s';
    private const NEXT_PROJECT_LIMIT = 1;
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
        private TranslatorInterface $translator,
        private CsrfTokenManagerInterface $csrfTokenManager,
    ) {}

    public function modify(ModifyProjectDTO $dto): Project
    {
        // Load the target project before validation so all checks run against the current persisted entity.
        $project = $this->findProjectByIdentifier($dto->projectId);

        if ($project === null) {
            throw new NotFoundHttpException('Project not found');
        }

        $this->assertProjectNotModifiedSinceLoaded($project, $dto);

        $this->assertProjectBelongsToOrganisation($project, $dto->organisationId);

        if ($dto->hasStatusNameChanged()) {
            $requestedStatusName = strtolower(trim((string) ($dto->statusName ?? '')));
            $currentStatusName = strtolower(trim((string) ($project->getStatus()?->getName() ?? '')));

            // Resolve and set status only when user actually changed it.
            if ($requestedStatusName !== '' && $requestedStatusName !== $currentStatusName) {
                $project->setStatus($this->resolveStatus($requestedStatusName));
            }
        }

        // Run all business-rule checks before copying form data onto the project entity.
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
        $organisationId = $this->getOrganisationIdFromRequest($request);
        $intent = $this->getRequestValue($request, 'intent');

        // Some submissions (e.g. programmatic form submits) may not include submitter name/value.
        // Treat POST without explicit intent as a standard modify action.
        if ($request->isMethod('POST') && $intent === '') {
            $intent = 'modify';
        }
        $fallbackId = trim($id) === '' ? 'unknown' : $id;

        try {
            $project = $this->findProjectByIdentifier($id);
            if (!$project instanceof Project) {
                throw new NotFoundHttpException($this->translateOrFallback('org.project.error.not_found_db', 'Project not found.'));
            }

            $resolvedProjectId = (string) $project->getId();
            $resolvedProjectRef = $this->projectRef($project);

            if ($organisationId === '' && $project->getOwnerOrganisation() !== null) {
                $organisationId = (string) $project->getOwnerOrganisation()->getId();
            }

            if ($intent !== '') {
                $redirect = $this->handleIntent(
                    $intent,
                    $request,
                    $handler,
                    $organisationId,
                    $resolvedProjectId,
                    $resolvedProjectRef,
                    $project,
                    $publisher
                );

                $redirectRoute = $redirect['redirectRoute'] ?? null;
                unset($redirect['redirectRoute']);

                $payload = ['redirect' => $redirect];
                if (is_string($redirectRoute) && $redirectRoute !== '') {
                    $payload['redirectRoute'] = $redirectRoute;
                }

                return $payload;
            }

            // Normalize route param to slug when available so URLs remain canonical.
            if ($id !== $resolvedProjectRef) {
                return ['redirect' => [
                    'id' => $resolvedProjectRef,
                ]];
            }

            return ['view' => [
                'id' => $resolvedProjectRef,
                'organisationId' => $organisationId,
                'project' => $this->mapProject($project, $organisationId),
                'visibilityOptions' => $this->buildVisibilityOptions(),
                'statusOptions' => $this->buildStatusOptions(),
                'error' => null,
            ]];
        } catch (HttpExceptionInterface $e) {
            if ($intent !== '') {
                return [
                    'redirectRoute' => 'org.modifyProject',
                    'redirect' => [
                        'id' => $fallbackId,
                        'status' => 'error',
                        'message' => $e->getMessage(),
                    ],
                ];
            }

            throw $e;
        }
    }

    public function delete(string $projectIdentifier, string $organisationId): Project
    {
        $project = $this->findProjectByIdentifier($projectIdentifier);

        if ($project === null) {
            throw new NotFoundHttpException('Project not found');
        }

        if ($organisationId === '' && $project->getOwnerOrganisation() !== null) {
            $organisationId = (string) $project->getOwnerOrganisation()->getId();
        }

        if ($project->getOwnerOrganisation() === null) {
            throw new AccessDeniedHttpException('Project does not belong to an organisation');
        }

        $projectOrganisationId = (string) $project->getOwnerOrganisation()->getId();
        if ($projectOrganisationId !== $organisationId) {
            throw new AccessDeniedHttpException('Project does not belong to the selected organisation');
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
        // The modify and delete actions both require a project-scoped CSRF token.
        if (in_array($intent, ['modify', 'delete'], true)) {
            if (!$this->isValidCsrfToken($request, $resolvedProjectId)) {
                return [
                    'redirectRoute' => 'org.modifyProject',
                    'id' => $resolvedProjectRef,
                    self::ORGANISATION_QUERY_KEY => $organisationId,
                    'status' => 'error',
                    'message' => $this->translateOrFallback('org.project.error.invalid_csrf', 'Invalid form token.'),
                ];
            }
        }

        if ($intent === 'delete') {
            try {
                $this->delete($resolvedProjectId, $organisationId);

                // After deletion, send the user to the next project in the organisation or back to the list.
                $nextProjectRef = $this->findAnotherProjectRefForOrganisation($organisationId, $resolvedProjectId);

                if ($nextProjectRef !== null) {
                    return [
                        'redirectRoute' => 'org.modifyProject',
                        'id' => $nextProjectRef,
                        'status' => 'success',
                        'message' => $this->translateOrFallback('org.project.deleted_success', 'Project deleted successfully.'),
                    ];
                }

                return [
                    'redirectRoute' => 'org.projects',
                    'status' => 'success',
                    'message' => $this->translateOrFallback('org.project.deleted_success', 'Project deleted successfully.'),
                ];
            } catch (HttpExceptionInterface $e) {
                return [
                    'redirectRoute' => 'org.modifyProject',
                    'id' => $resolvedProjectRef,
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];
            } catch (\Throwable) {
                return [
                    'redirectRoute' => 'org.modifyProject',
                    'id' => $resolvedProjectRef,
                    'status' => 'error',
                    'message' => 'Failed to delete project.',
                ];
            }
        }

        // Replace any slug or route alias with the stored project id before the handler reads the request.
        $request->request->set('project_id', $resolvedProjectId);

        if ($project instanceof Project) {
            $request->attributes->set('_current_project', $project);
        }

        $result = $handler->handle($request, $publisher);

        return [
            'redirectRoute' => 'org.modifyProject',
            'id' => $resolvedProjectRef,
            'status' => !empty($result['success']) ? 'success' : 'error',
            'message' => !empty($result['success'])
            ? $this->translateOrFallback('org.project.updated_success', 'Project updated successfully.')
            : ($result['error'] ?? $this->translateOrFallback('org.project.update_failed', 'Failed to update project.')),
        ];
    }

    private function getOrganisationIdFromRequest(Request $request): string
    {
        return $this->getRequestValueFromAliases($request, [
            self::ORGANISATION_QUERY_KEY,
            'organization_id',
            'organisationId',
            'organizationId',
        ]);
    }

    private function getRequestValue(Request $request, string $key): string
    {
        return trim((string) ($request->request->get($key) ?? $request->query->get($key) ?? ''));
    }

    private function getRequestValueFromAliases(Request $request, array $keys): string
    {
        foreach ($keys as $key) {
            $value = $this->getRequestValue($request, $key);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function findProjectByIdentifier(string $identifier): ?Project
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        $projectRepository = $this->entityManager->getRepository(Project::class);

        // Accept both UUIDs and slugs so the route can be called with either the canonical id or the readable alias.
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $identifier)) {
            $project = $projectRepository->find($identifier);
            if ($project instanceof Project) {
                return $project;
            }
        }

        $project = $projectRepository->findOneBy(['slug' => $identifier]);

        return $project instanceof Project ? $project : null;
    }

    private function findAnotherProjectRefForOrganisation(string $organisationId, string $excludedProjectId): ?string
    {
        if ($organisationId === '') {
            return null;
        }

        // Find the newest remaining project in the same organisation so delete redirects stay inside the project area.
        $qb = $this->entityManager->getRepository(Project::class)->createQueryBuilder('p');
        $project = $qb
            ->where('p.ownerOrganisation = :organisationId')
            ->andWhere('p.id <> :excludedProjectId')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('organisationId', $organisationId)
            ->setParameter('excludedProjectId', $excludedProjectId)
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults(self::NEXT_PROJECT_LIMIT)
            ->getQuery()
            ->getOneOrNullResult();

        return $project instanceof Project ? $this->projectRef($project) : null;
    }

    private function projectRef(Project $project): string
    {
        // Keep modify URLs canonical on UUID to avoid mixed slug/id navigation.
        return (string) $project->getId();
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
            'statusId' => $project->getStatus() ? (string) $project->getStatus()->getId() : null,
            'organisationId' => $project->getOwnerOrganisation() ? (string) $project->getOwnerOrganisation()->getId() : $organisationId,
            'startDate' => $project->getStartDate()?->format(self::DATE_FORMAT),
            'endDate' => $project->getEndDate()?->format(self::DATE_FORMAT),
            'capacity' => $project->getCapacity(),
            'lastModified' => $project->getLastModified()?->format(self::DATE_TIME_FORMAT),
        ];
    }

    private function assertProjectNotModifiedSinceLoaded(Project $project, ModifyProjectDTO $dto): void
    {
        // Stop the update when the database version changed after the form was loaded.
        if ($dto->expectedLastModified === null) {
            return;
        }

        $current = $project->getLastModified();
        if ($current === null) {
            return;
        }

        if ($current->format(self::DATE_TIME_FORMAT) !== $dto->expectedLastModified->format(self::DATE_TIME_FORMAT)) {
            throw new \RuntimeException($this->translateOrFallback('org.project.error.concurrent_modification', 'Project was modified by another request. Please reload and try again.'));
        }
    }

    private function isValidCsrfToken(Request $request, string $projectId): bool
    {
        $tokenValue = trim((string) ($request->request->get('_token') ?? $request->query->get('_token') ?? ''));
        // Scope the CSRF token to the project id so a token from another project cannot be replayed here.
        $tokenId = 'org_modify_project_' . $projectId;

        return $tokenValue !== '' && $this->csrfTokenManager->isTokenValid(new CsrfToken($tokenId, $tokenValue));
    }

    private function assertProjectBelongsToOrganisation(Project $project, string $organisationId): void
    {
        if ($project->getOwnerOrganisation() === null) {
            throw new AccessDeniedHttpException('Project does not belong to an organisation');
        }

        $projectOrganisationId = (string) $project->getOwnerOrganisation()->getId();
        if ($projectOrganisationId !== $organisationId) {
            throw new AccessDeniedHttpException('Project does not belong to the selected organisation');
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
        if (!$dto->hasCapacityChanged() || $dto->capacity === null) {
            return;
        }

        if ($dto->capacity < self::CAPACITY_MIN || $dto->capacity > self::CAPACITY_MAX) {
            throw new \InvalidArgumentException(
                'Capacity must be between ' . self::CAPACITY_MIN . ' and ' . self::CAPACITY_MAX . '.'
            );
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

        $statusName = strtolower((string) ($project->getStatus()?->getName() ?? ''));
        if ($statusName === 'published' && $project->getPublishedAt() === null) {
            $project->publish();
        }

        if ($statusName === 'draft' || $statusName === 'archived') {
            $project->unpublish();
        }
    }

    private function buildVisibilityOptions(): array
    {
        // TODO: Replace this placeholder with DB-backed visibility options.
        // For now we expose ALLOWED_VISIBILITY so UI values and validation stay in sync.
        return array_map(static function (string $value): array {
            return [
                'value' => $value,
                'label' => ucfirst($value),
            ];
        }, self::ALLOWED_VISIBILITY);
    }

    private function buildStatusOptions(): array
    {
        $statuses = $this->entityManager
            ->getRepository(Status::class)
            ->createQueryBuilder('s')
            ->where('s.scope = :scope')
            ->setParameter('scope', 'project')
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(static function (Status $status): array {
            return [
                'value' => (string) $status->getId(),
                'label' => ucfirst((string) $status->getName()),
                'name' => strtolower((string) $status->getName()),
            ];
        }, $statuses);
    }

    private function getCharacterLength(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value, 'UTF-8');
        }

        return strlen($value);
    }

    private function resolveStatus(?string $statusRef): Status
    {
        $statusRef = trim((string) $statusRef);
        if ($statusRef === '') {
            throw new \InvalidArgumentException('Status is required');
        }

        try {
            $statusById = $this->entityManager->getRepository(Status::class)->find($statusRef);
            if ($statusById instanceof Status && $statusById->getScope() === 'project') {
                return $statusById;
            }
        } catch (\Throwable) {
            // Not an id value for this platform/type: continue with name-based lookup.
        }

        $statusName = strtolower($statusRef);

        $status = $this->entityManager
            ->getRepository(Status::class)
            ->createQueryBuilder('s')
            ->where('LOWER(s.name) = :name')
            ->andWhere('s.scope = :scope')
            ->setParameter('name', $statusName)
            ->setParameter('scope', 'project')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$status instanceof Status) {
            throw new BadRequestHttpException('Unknown project status: ' . $statusRef);
        }

        return $status;
    }

    private function translateOrFallback(string $key, string $fallback): string
    {
        $translated = $this->translator->trans($key);
        if ($translated === '' || $translated === $key) {
            return $fallback;
        }

        return $translated;
    }
}
