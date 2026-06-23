<?php

namespace App\Module\Org\Mapper;

use App\Entity\Project\Project;
use App\Module\Org\DTO\ModifyProjectDTO;
use Symfony\Component\HttpFoundation\Request;

class ModifyProjectMapper
{
    private const DATE_FORMAT = 'Y-m-d';
    private const DATE_TIME_FORMAT = 'Y-m-d H:i:s';

    public function fromRequest(Request $request, array $extraData = []): ModifyProjectDTO
    {
        $title = $this->getStringFromRequest($request, ['title', 'name']);

        return new ModifyProjectDTO(
            projectId: (string) $request->request->get('project_id', $extraData['project_id'] ?? $request->attributes->get('id', '')),
            organisationId: (string) $request->request->get('organisation_id', $extraData['organisation_id'] ?? ''),
            title: $title,
            summary: $this->getStringFromRequest($request, ['summary']),
            description: $this->getStringFromRequest($request, ['description']),
            visibility: $this->getStringFromRequest($request, ['visibility']),
            statusName: $this->normalizeStatusName($this->getStringFromRequest($request, ['status', 'status_name'])),
            startDate: $this->getDateFromRequest($request, ['start_date', 'startDate']),
            endDate: $this->getDateFromRequest($request, ['end_date', 'endDate']),
            capacity: $this->getIntFromRequest($request, ['capacity']),
            modifiedBy: $extraData['publisher'] ?? null,
            modifiedAt: new \DateTimeImmutable(),
        );
    }

    public function toEntity(Project $project, ModifyProjectDTO $dto): Project
    {
        if ($dto->hasTitleChanged()) {
            $project->setTitle($dto->title);
        }

        if ($dto->hasSummaryChanged()) {
            $project->setSummary($dto->summary);
        }

        if ($dto->hasDescriptionChanged()) {
            $project->setDescription($dto->description);
        }

        if ($dto->hasVisibilityChanged()) {
            $project->setVisibility($dto->visibility);
        }

        if ($dto->hasStartDateChanged()) {
            $project->setStartDate($dto->startDate);
        }

        if ($dto->hasEndDateChanged()) {
            $project->setEndDate($dto->endDate);
        }

        if ($dto->hasCapacityChanged()) {
            $project->setCapacity($dto->capacity);
        }

        return $project;
    }

    public function toResponse(Project $project): array
    {
        return [
            'success' => true,
            'id' => (string) $project->getId(),
            'organisationId' => $project->getOwnerOrganisation() ? (string) $project->getOwnerOrganisation()->getId() : null,
            'title' => $project->getTitle(),
            'name' => $project->getTitle(),
            'summary' => $project->getSummary(),
            'description' => $project->getDescription(),
            'visibility' => $project->getVisibility(),
            'status' => $project->getStatus()?->getName(),
            'statusName' => $project->getStatus()?->getName(),
            'statusId' => $project->getStatus()?->getId(),
            'startDate' => $project->getStartDate()?->format(self::DATE_FORMAT),
            'endDate' => $project->getEndDate()?->format(self::DATE_FORMAT),
            'capacity' => $project->getCapacity(),
            'effectiveAt' => $project->getLastModified()?->format(self::DATE_TIME_FORMAT),
            'published_at' => $project->getPublishedAt()?->format(self::DATE_TIME_FORMAT),
            'last_modified' => $project->getLastModified()?->format(self::DATE_TIME_FORMAT),
            'url' => '/org/projects/modify/' . $project->getId(),
        ];
    }

    public function toErrorResponse(string $error, int $code = 400): array
    {
        return [
            'success' => false,
            'error' => $error,
            'code' => $code,
        ];
    }

    private function getStringFromRequest(Request $request, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $request->request->get($key);
            if ($value === null) {
                continue;
            }

            $value = trim((string) $value);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function getDateFromRequest(Request $request, array $keys): ?\DateTimeImmutable
    {
        $date = $this->getStringFromRequest($request, $keys);
        if ($date === null) {
            return null;
        }

        try {
            return new \DateTimeImmutable($date);
        } catch (\Throwable) {
            return null;
        }
    }

    private function getIntFromRequest(Request $request, array $keys): ?int
    {
        $value = $this->getStringFromRequest($request, $keys);
        if ($value === null) {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    private function getBoolFromRequest(Request $request, array $keys): ?bool
    {
        $value = $this->getStringFromRequest($request, $keys);
        if ($value === null) {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    }

    private function normalizeStatusName(?string $statusName): ?string
    {
        if ($statusName === null) {
            return null;
        }

        $statusName = trim(strtolower($statusName));

        return match ($statusName) {
            '1', 'draft' => 'draft',
            '2', 'published', 'active' => 'published',
            '3', 'archived', 'closed' => 'archived',
            default => $statusName === '' ? null : $statusName,
        };
    }
}
